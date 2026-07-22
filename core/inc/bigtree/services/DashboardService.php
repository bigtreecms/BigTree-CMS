<?php
	namespace BigTree\Services;

	use BigTree\Api\Json;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Services\AI\Tools\DashboardToolBackend;
	use BigTreeGoogleAnalytics4;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Consolidated dashboard summary for the SPA landing page. One endpoint that
	 * returns the per-user data the legacy admin dashboard would normally take
	 * several round-trips to fetch:
	 *
	 *   - unread message count
	 *   - pending changes you own (count) and pending changes you can publish (count)
	 *   - recent audit activity (your recent changes)
	 *   - 404 totals (level >= 1)
	 *   - integrity check totals (level >= 1)
	 *
	 * One round-trip → snappy initial render.
	 */
	class DashboardService implements DashboardToolBackend {
		public function summary(Request $request) {
			$me = $request->user;
			$me_id = (int)$me->id;

			$data = [
				"user" => [
					"id" => $me_id,
					"name" => $me->name,
					"email" => $me->email,
					"level" => (int)$me->level,
				],
				"messages" => $this->messageStats($me_id),
				"pending_changes" => $this->pendingChangeStats($me),
				"recent_activity" => $this->recentActivity($me_id, 10),
			];

			if ((int)$me->level >= 1) {
				$data["404s"] = $this->fourOhFourStats();
				$data["integrity"] = $this->integrityStats();
			}

			return Response::ok($data)->cacheFor(15);
		}

		public function contentAlerts(Request $request) {
			$me_id = (int)$request->user->id;
			$alerts_json = SQL::fetchSingle("SELECT alerts FROM bigtree_users WHERE id = ?", $me_id);
			$alerts = Json::decode($alerts_json);
			$out = [];

			foreach ($alerts as $page_id => $days_threshold) {
				$days_threshold = (int)$days_threshold;

				if ($days_threshold <= 0) {
					continue;
				}
				$row = SQL::fetch(
					"SELECT id, nav_title, path, updated_at,
					        DATEDIFF(NOW(), updated_at) AS age_days
					 FROM bigtree_pages
					 WHERE id = ? AND DATEDIFF(NOW(), updated_at) >= ?",
					(int)$page_id, $days_threshold
				);

				if ($row) {
					$out[] = [
						"page_id" => (int)$row["id"],
						"nav_title" => Sanitize::decodeEntities($row["nav_title"]),
						"path" => $row["path"],
						"updated_at" => $row["updated_at"],
						"age_days" => (int)$row["age_days"],
						"threshold_days" => $days_threshold,
					];
				}
			}

			return Response::ok($out);
		}

		public function integrity(Request $request) {

			return Response::ok($this->integrityStats(true));
		}

		// — AI tool seam (DashboardToolBackend) —
		//
		// Read-only and level-0, matching the dashboard panel this mirrors. The one
		// difference: the dashboard renders for its own owner, whereas a tool result
		// is read back by a model, so every row is filtered through the caller's page
		// view access rather than assumed visible.

		/**
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiContentAlerts($user, int $limit): array {
			$limit = max(1, min(100, $limit));

			// The site-wide rule: a page carrying its own max_age that hasn't been
			// touched in that many days.
			$rows = SQL::fetchAll(
				"SELECT id, nav_title, path, updated_at, max_age,
				        DATEDIFF(NOW(), updated_at) AS age_days
				 FROM bigtree_pages
				 WHERE max_age > 0 AND archived = '' AND DATEDIFF(NOW(), updated_at) > max_age
				 ORDER BY age_days DESC"
			);
			$stale = [];

			foreach ($rows as $row) {
				if (count($stale) >= $limit) {

					break;
				}

				if (!PermissionService::userHasPageAccess($user, (int)$row["id"], "v")) {

					continue;
				}

				$stale[] = [
					"page_id" => (int)$row["id"],
					"nav_title" => Sanitize::decodeEntities($row["nav_title"]),
					"path" => "/" . (string)$row["path"],
					"updated_at" => $row["updated_at"],
					"age_days" => (int)$row["age_days"],
					"max_age_days" => (int)$row["max_age"],
				];
			}

			// The caller's own watch list, which is what GET /dashboard/content-alerts
			// returns. Keyed by page id → threshold in days.
			$user_id = is_object($user) ? (int)($user->id ?? 0) : (int)($user["id"] ?? 0);
			$alerts = Json::decode(SQL::fetchSingle("SELECT alerts FROM bigtree_users WHERE id = ?", $user_id));
			$watched = [];

			foreach ($alerts as $page_id => $threshold) {
				$threshold = (int)$threshold;

				if ($threshold <= 0 || count($watched) >= $limit) {

					continue;
				}

				if (!PermissionService::userHasPageAccess($user, (int)$page_id, "v")) {

					continue;
				}

				$row = SQL::fetch(
					"SELECT id, nav_title, path, updated_at, DATEDIFF(NOW(), updated_at) AS age_days
					 FROM bigtree_pages
					 WHERE id = ? AND DATEDIFF(NOW(), updated_at) >= ?",
					(int)$page_id,
					$threshold
				);

				if ($row) {
					$watched[] = [
						"page_id" => (int)$row["id"],
						"nav_title" => Sanitize::decodeEntities($row["nav_title"]),
						"path" => "/" . (string)$row["path"],
						"updated_at" => $row["updated_at"],
						"age_days" => (int)$row["age_days"],
						"threshold_days" => $threshold,
					];
				}
			}

			return [
				"stale" => $stale,
				"watched" => $watched,
				"note" => (!$stale && !$watched)
					? "No content is currently flagged as stale. A page is only tracked once it has a max_age "
						. "(set it with update_page) or a personal alert threshold set in the admin."
					: "\"stale\" is the site-wide rule (a page past its own max_age); \"watched\" is your personal "
						. "alert list. Both are filtered to pages you can view.",
			];
		}

		// — internals —

		private function messageStats($me_id) {
			$unread = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_messages
				 WHERE recipients LIKE ? AND (read_by NOT LIKE ? OR read_by IS NULL)",
				"%|$me_id|%", "%|$me_id|%"
			);
			$total_in = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_messages WHERE recipients LIKE ?", "%|$me_id|%");

			return ["unread" => $unread, "total_in" => $total_in];
		}

		private function pendingChangeStats($user) {
			$me_id = (int)$user->id;
			$mine = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes WHERE user = ?", $me_id);

			// Publishable: easy when admin/dev; for editors we'd have to filter by module
			// permission per row, which is expensive — return null + a flag instead so the
			// SPA knows to call /pending-changes for the precise list.
			if ((int)$user->level >= 1) {
				$publishable = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes");
			} else {
				$publishable = null;
			}

			return ["mine" => $mine, "publishable" => $publishable];
		}

		private function recentActivity($me_id, $limit) {
			// LIMIT can't take a ? placeholder — BigTree's SQL::query substitutes
			// ? with '<quoted value>' (escaped string literal), and MySQL rejects
			// LIMIT '10'. Cast to int and interpolate directly; integer cast makes
			// this safe from injection.
			$limit = max(1, (int)$limit);
			$rows = SQL::fetchAll(
				"SELECT id, `table`, entry, type, date FROM bigtree_audit_trail
				 WHERE user = ? ORDER BY date DESC, id DESC LIMIT $limit",
				$me_id
			);

			return array_map(function ($r) {

				return [
					"id" => (int)$r["id"],
					"table" => $r["table"],
					"entry" => $r["entry"],
					"type" => $r["type"],
					"date" => $r["date"],
				];
			}, $rows);
		}

		private function fourOhFourStats() {

			return [
				"unresolved" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE redirect_url = '' AND ignored = ''"),
				"redirects" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE redirect_url != '' AND ignored = ''"),
				"ignored" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = 'on'"),
			];
		}

		private function integrityStats($verbose = false) {
			// Lightweight signals only — the legacy "Integrity Check" page does heavier
			// per-row resource/IPL/IRL walks; we expose just the cheap headline numbers.
			$missing_templates = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_pages
				 WHERE template != '' AND template NOT IN (
					SELECT 'placeholder' WHERE FALSE
				 )"
			);
			// Templates live in JSONDB so we can't JOIN — do a small Php-side check instead.
			$templates = BigTreeJSONDB::getAll("templates");
			$valid_ids = array_flip(array_column($templates, "id"));
			$missing = SQL::fetchAll("SELECT id, template FROM bigtree_pages WHERE template != ''");
			$broken_pages = [];

			foreach ($missing as $row) {
				if (!isset($valid_ids[$row["template"]])) {
					$broken_pages[] = ["id" => (int)$row["id"], "template" => $row["template"]];
				}
			}

			$result = [
				"pages_with_missing_template" => count($broken_pages),
				"templates_total" => count($templates),
				"pages_total" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages"),
				"resources_total" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_resources"),
				"orphan_resource_allocations" => (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_resource_allocation a
					 LEFT JOIN bigtree_resources r ON r.id = a.resource
					 WHERE r.id IS NULL"
				),
			];

			if ($verbose) {
				$result["broken_pages"] = $broken_pages;
			}

			return $result;
		}

		// — Analytics (GA4) —

		const ANALYTICS_CACHE_FILE = "cache/analytics.json";

		/**
		 * GET /dashboard/analytics
		 *
		 * Returns the cached GA4 data (referrers, browsers, year/quarter/month rollups
		 * with year-ago comparisons, and a 2-week sessions-per-day series for charting).
		 *
		 * Read-only — the SPA pulls this on dashboard load. To refresh the underlying
		 * data, POST /dashboard/analytics/cache. Cache rebuild is slow (multiple GA4
		 * round-trips) so we never trigger it implicitly on read.
		 *
		 * If GA4 is not configured, returns 200 with { configured: false } so the SPA
		 * can render a "connect Google Analytics" prompt rather than a 404.
		 */
		public function analytics(Request $request) {
			$status = $this->analyticsStatus();

			$cache_path = SERVER_ROOT . self::ANALYTICS_CACHE_FILE;
			$has_cache = file_exists($cache_path);
			$cache = $has_cache ? json_decode(@file_get_contents($cache_path), true) : null;
			$cache_mtime = $has_cache ? (int)@filemtime($cache_path) : null;

			$response = array_merge($status, [
				"cache" => is_array($cache) ? $cache : null,
				"cache_at" => $cache_mtime ? date("c", $cache_mtime) : null,
				"cache_age_seconds" => $cache_mtime ? (time() - $cache_mtime) : null,
			]);

			// Short cache so the SPA can refresh without hitting us every render,
			// but short enough that a manual rebuild becomes visible quickly.
			return Response::ok($response)->cacheFor(30);
		}

		/**
		 * GET /dashboard/analytics/status
		 *
		 * Lighter-weight than /analytics — returns just connection + cache age. Useful
		 * for SPA polling after a rebuild kick-off.
		 */
		public function analyticsStatusEndpoint(Request $request) {
			return Response::ok($this->analyticsStatus());
		}

		/**
		 * POST /dashboard/analytics/cache
		 *
		 * Triggers cacheInformation() which makes a series of GA4 reportRun calls and
		 * rewrites cache/analytics.json. This is synchronous and can take minutes on
		 * large sites — clients should set a generous timeout. v1 ships without a
		 * background-job runner; a future v2 can wrap this in an async pattern.
		 *
		 * Returns 200 with the new cache_at on success. Returns 503 with
		 * { code: analytics_not_configured } if GA4 isn't set up — the SPA should
		 * direct the user to /developer/analytics/ to connect credentials.
		 *
		 * Concurrency: a sentinel file (cache/analytics-building.flag) is created
		 * during the run so concurrent rebuild requests fast-fail with 409. The
		 * sentinel auto-expires after 10 minutes in case the previous run crashed.
		 */
		public function rebuildAnalyticsCache(Request $request) {
			$status = $this->analyticsStatus();
			if (!$status["configured"]) {
				throw new BadRequestException("Google Analytics is not configured", "analytics_not_configured", 503);
			}

			$sentinel = SERVER_ROOT . "cache/analytics-building.flag";
			if (file_exists($sentinel) && (time() - @filemtime($sentinel)) < 600) {
				$age = time() - @filemtime($sentinel);
				$conflict = new \BigTree\Api\Exceptions\ConflictException("Analytics cache rebuild already in progress (started {$age}s ago)", "analytics_rebuild_in_progress");
				throw $conflict;
			}

			@file_put_contents($sentinel, (string)time());

			$started_at = microtime(true);
			try {
				$analytics = new BigTreeGoogleAnalytics4();
				$analytics->cacheInformation();
			} catch (\Throwable $e) {
				@unlink($sentinel);
				// Surface the GA4 client error to the caller — useful when credentials
				// have expired or the property id has been removed.
				throw new BadRequestException(
					"Analytics cache rebuild failed: " . $e->getMessage(),
					"analytics_rebuild_failed",
					502
				);
			}
			@unlink($sentinel);

			$elapsed_ms = (int)round((microtime(true) - $started_at) * 1000);
			$cache_path = SERVER_ROOT . self::ANALYTICS_CACHE_FILE;
			return Response::ok([
				"refreshed" => true,
				"elapsed_ms" => $elapsed_ms,
				"cache_at" => file_exists($cache_path) ? date("c", @filemtime($cache_path)) : null,
			]);
		}

		// — analytics internals —

		private function analyticsStatus() {
			// Lightweight check — read the setting directly so we don't construct the
			// BigTreeGoogleAnalytics4 client unless we need it (avoids the OAuth/SDK init).
			$settings = \BigTreeCMS::getSetting("bigtree-internal-google-analytics-4");
			$settings = is_array($settings) ? $settings : [];

			$configured = !empty($settings["credentials"]) && !empty($settings["property_id"]);
			$verified = !empty($settings["verified"]);

			$cache_path = SERVER_ROOT . self::ANALYTICS_CACHE_FILE;
			$has_cache = file_exists($cache_path);

			return [
				"configured" => $configured,
				"verified" => $verified,
				"property_id" => $settings["property_id"] ?? null,
				"has_cache" => $has_cache,
				"cache_at" => $has_cache ? date("c", @filemtime($cache_path)) : null,
				"cache_age_seconds" => $has_cache ? (time() - (int)@filemtime($cache_path)) : null,
			];
		}
	}
