<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Pagination;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Upload;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\PayloadBudget;
	use BigTree\Services\AI\PreviewValue;
	use BigTree\Services\AI\Tools\FourOhFourToolBackend;
	use BigTree\Services\AI\Tools\RedirectToolBackend;
	use BigTree;
	use SQL;
	use BigTreeCMS;

	/**
	 * Manage the bigtree_404s table: 404 log, 301 redirects, ignored URLs.
	 * type filter: 404 | 301 | ignored
	 */
	class FourOhFourService implements RedirectToolBackend, FourOhFourToolBackend {
		/** The most 404 log rows one list_404s call returns, matching GET /404s' per_page cap. */
		public const AI_404_LIMIT = 100;

		/** "Clear" removes active 404s with fewer hits than this. */
		public const CLEAR_BELOW_REQUESTS = 5;

		/** How far a redirect chain is followed — checkOldRoutes' own `max_depth`. */
		private const AI_REDIRECT_CHAIN_DEPTH = 5;

		public function list(Request $request) {
			$type = $request->query["type"] ?? "404";
			$site_key = $request->query["site_key"] ?? null;
			$q = $request->queryString("q");

			[$where, $args] = $this->buildConditions($type, $site_key, $q);

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_404s" . $where,
				"SELECT * FROM bigtree_404s" . $where . " ORDER BY requests DESC, id DESC",
				$args,
				// Closure required: private method arrays fail the `callable` type
				// check when passed across class boundaries into Pagination.
				function ($row) {
					return $this->present($row);
				},
				100
			);
		}

		/**
		 * Unpaginated dump of every row for a bucket, used by the SPA's "Export
		 * CSV" action (the SPA serializes the rows client-side, mirroring the
		 * legacy dashboard's streamed CSV export).
		 */
		public function export(Request $request) {
			$type = $request->query["type"] ?? "404";
			$site_key = $request->query["site_key"] ?? null;

			[$where, $args] = $this->buildConditions($type, $site_key, "");

			// Hard ceiling so an "Export CSV" click can't OOM the box on a site with a
			// huge 404 log. Fetch in chunks to keep peak memory flat; the list endpoint
			// paginates for the same reason. Rows are ordered by request volume so the
			// cap keeps the most relevant entries. LIMIT/OFFSET are integer-cast and
			// interpolated exactly as list() does (a `?` placeholder would quote them).
			$max = 50000;
			$chunk = 1000;
			$out = [];

			for ($offset = 0; $offset < $max; $offset += $chunk) {
				$limit = min($chunk, $max - $offset);
				$rows = SQL::fetchAll(...array_merge([
					"SELECT * FROM bigtree_404s" . $where .
					" ORDER BY requests DESC, id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
				], $args));

				if (!$rows) {
					break;
				}

				foreach ($rows as $r) {
					$out[] = $this->present($r);
				}

				if (count($rows) < $limit) {
					break;
				}
			}

			return Response::ok($out, ["capped" => count($out) >= $max, "max" => $max]);
		}

		/**
		 * Build the WHERE clause + bound args shared by list/export. `type`
		 * selects the bucket (404 / 301 / ignored); `site_key` and `q` are
		 * optional filters (`q` matches the broken or redirect URL).
		 */
		private function buildConditions(string $type, ?string $site_key, string $q): array {
			$conds = [];
			$args = [];

			switch ($type) {
				case "404":
					$conds[] = "redirect_url = ''";
					$conds[] = "ignored = ''";
					break;

				case "301":
					$conds[] = "redirect_url != ''";
					$conds[] = "ignored = ''";
					break;

				case "ignored":
					$conds[] = "ignored = 'on'";
					break;

				default:
					throw new BadRequestException("type must be 404, 301 or ignored", "bad_type");
			}

			if ($site_key !== null) {
				$conds[] = "site_key = ?";
				$args[] = $site_key;
			}

			if ($q !== "") {
				$conds[] = "(broken_url LIKE ? OR redirect_url LIKE ?)";
				$like = Sanitize::likeTerm($q);
				$args[] = $like;
				$args[] = $like;
			}

			$where = $conds ? " WHERE " . implode(" AND ", $conds) : "";

			return [$where, $args];
		}

		/**
		 * Manually add a single 301 redirect. Runs through
		 * FourOhFourService::create301 so the source URL is parsed (full URL or
		 * domain-relative fragment), the site key inferred where possible, the
		 * destination converted to an internal-page link, and any existing entry
		 * for the same source updated rather than duplicated.
		 */
		public function create(Request $request) {
			$d = $request->body;
			$from = trim((string)$d["from"]);
			$to = trim((string)$d["to"]);
			$site_key = trim((string)($d["site_key"] ?? "")) ?: null;

			$actor_id = isset($request->user) ? (int)($request->user->id ?? 0) ?: null : null;
			self::create301($from, $to, $site_key, $actor_id);

			$parsed = self::parse404SourceURL($from, $site_key);
			$row = self::getExisting404($parsed["url"], $parsed["get_vars"], $parsed["site_key"]);

			return Response::created($this->present($row), null);
		}

		public function delete(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_404s", $id, "404");

			SQL::delete("bigtree_404s", $id);

			return Response::noContent();
		}

		public function setRedirect(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_404s", $id, "404");

			SQL::update("bigtree_404s", $id, ["redirect_url" => $request->bodyString("url", "", false), "ignored" => ""]);

			return Response::ok($this->present(SQL::fetch("SELECT * FROM bigtree_404s WHERE id = ?", $id)));
		}

		public function ignore(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_404s", $id, "404");

			SQL::update("bigtree_404s", $id, ["ignored" => "on"]);

			return Response::noContent();
		}

		public function unignore(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_404s", $id, "404");

			SQL::update("bigtree_404s", $id, ["ignored" => ""]);

			return Response::noContent();
		}

		/**
		 * Prune low-traffic 404s: active (not ignored, not redirected) entries with
		 * fewer than CLEAR_BELOW_REQUESTS hits, which are mostly one-off typos and
		 * crawler noise. Narrower than legacy's clearDead404s, which deleted every
		 * entry without a redirect (ignored ones too). Scoped to one site when
		 * `site_key` is sent.
		 */
		public function clearDead(Request $request) {
			$where = "ignored = '' AND redirect_url = ''";
			$args = [];

			if (array_key_exists("site_key", $request->body)) {
				$where .= " AND site_key = ?";
				$args[] = $request->bodyString("site_key");
			}

			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE $where", ...$args);
			SQL::query(
				"DELETE FROM bigtree_404s WHERE $where AND requests < " . self::CLEAR_BELOW_REQUESTS,
				...$args
			);
			$now = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE $where", ...$args);

			return Response::ok(["before" => $count, "after" => $now, "deleted" => $count - $now]);
		}

		public function bulkDelete(Request $request) {
			$ids = $request->bodyList("ids", "int");

			if (!$ids) {
				return Response::noContent();
			}
			$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
			SQL::query(...array_merge(["DELETE FROM bigtree_404s WHERE id IN ($placeholders)"], $ids));

			return Response::noContent();
		}

		/**
		 * Bulk-import 301 redirects from an uploaded CSV (one `from,to` pair per
		 * row). Mirrors the legacy dashboard import: each row runs through
		 * FourOhFourService::create301, which parses the source URL, de-dupes against
		 * existing entries (updating rather than duplicating), converts internal
		 * link targets to IPLs, and cleans stale route history. A header row whose
		 * first cell looks like a label ("from"/"source"/"url") is skipped.
		 */
		public function importCsv(Request $request) {
			$file = Upload::requireSingle($request, "file");
			$handle = fopen($file["tmp_name"], "r");

			if ($handle === false) {
				throw new BadRequestException("Could not read the uploaded file.", "unreadable_file");
			}

			$site_key = $request->bodyString("site_key") ?: null;
			$first_row_titles = $request->bodyBool("first_row_titles");
			$actor_id = isset($request->user) ? (int)($request->user->id ?? 0) ?: null : null;

			$imported = 0;
			$skipped = 0;
			$row_num = 0;

			while (($row = fgetcsv($handle, 0, ",", '"')) !== false) {
				$row_num++;

				if (!is_array($row) || count($row) < 2) {
					$skipped++;

					continue;
				}

				$from = trim((string)$row[0]);
				$to = trim((string)$row[1]);

				// Skip the first row when the user flagged it as column titles,
				// or when it heuristically looks like a header.
				if ($row_num === 1 && ($first_row_titles || in_array(strtolower($from), ["from", "source", "url", "broken_url"], true))) {
					continue;
				}

				if ($from === "" || $to === "") {
					$skipped++;

					continue;
				}

				self::create301($from, $to, $site_key, $actor_id);
				$imported++;
			}

			fclose($handle);

			return Response::ok(["imported" => $imported, "skipped" => $skipped]);
		}

		private function present(array $r) {

			return [
				"id" => (int)$r["id"],
				"broken_url" => $r["broken_url"],
				"get_vars" => $r["get_vars"],
				"redirect_url" => $r["redirect_url"],
				"requests" => (int)$r["requests"],
				"ignored" => Flag::isOn($r["ignored"]),
				"site_key" => $r["site_key"],
				"type" => Flag::isOn($r["ignored"]) ? "ignored" : ($r["redirect_url"] !== "" ? "301" : "404"),
			];
		}
	
		public static function parse404SourceURL($source, $site_key = null) {
			global $bigtree;

			$source = trim($source);

			// If this is a multi-site environment and a full URL was pasted in we're going to auto-select the key no matter what they passed in
			if (!is_null($site_key)) {
				$from_domain = parse_url($source, PHP_URL_HOST);

				foreach ($bigtree["config"]["sites"] as $index => $site) {
					$domain = parse_url($site["domain"], PHP_URL_HOST);

					if ($domain == $from_domain) {
						$site_key = $index;
						$source = str_replace($site["www_root"], "", $source);
					}
				}
			}

			// Allow for from URLs with GET vars
			$source_parts = parse_url($source);
			$get_vars = "";

			if (!empty($source_parts["query"])) {
				$source = str_replace("?".$source_parts["query"], "", $source);
				$get_vars = sqlescape(htmlspecialchars($source_parts["query"]));
			}

			return [
				"url" => htmlspecialchars(strip_tags(trim(str_replace(WWW_ROOT, "", $source), "/"))),
				"get_vars" => $get_vars,
				"site_key" => $site_key
			];
		}

		public static function getExisting404($url, $get_vars, $site_key = null) {
			if (!empty($get_vars)) {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ? AND `site_key` = ?", $url, $get_vars, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ?", $url, $get_vars);
				}
			} else {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = '' AND `site_key` = ?", $url, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ''", $url);
				}
			}
		}

		public static function create301($from, $to, $site_key = null, $actor_id = null) {
			global $bigtree;

			// See if the from already exists
			$sanitized_input = static::parse404SourceURL($from, $site_key);
			$from = $sanitized_input["url"];
			$get_vars = $sanitized_input["get_vars"];
			$site_key = $sanitized_input["site_key"];
			$to = sqlescape(htmlspecialchars(LinkService::autoIPL(trim($to))));
			$existing = static::getExisting404($from, $get_vars, $site_key);

			// Route history is keyed by the stored page path, which on a multi-site
			// install carries the site's trunk path; the 404 row does not.
			SQL::delete("bigtree_route_history", ["old_route" => ltrim(static::sitePathFor($site_key) . "/" . $from, "/")]);

			if ($existing) {
				// `ignored` is cleared in the same write REST's setRedirect does it in:
				// a row that redirects at request time but is still flagged ignored is
				// bucketed as type "ignored" by present() and never appears in the 301
				// list, so the redirect exists and is invisible to whoever made it.
				sqlquery("UPDATE bigtree_404s SET `redirect_url` = '$to', `ignored` = '' WHERE id = '".$existing["id"]."'");
				if ($actor_id !== null) { AuditService::write("bigtree_404s", $existing["id"], "updated", $actor_id); }
			} else {
				if (!is_null($site_key)) {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`, `get_vars`, `redirect_url`, `site_key`) VALUES ('$from', '$get_vars', '$to', '".sqlescape($site_key)."')");
				} else {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`, `get_vars`, `redirect_url`) VALUES ('$from', '$get_vars', '$to')");
				}

				if ($actor_id !== null) { AuditService::write("bigtree_404s", sqlid(), "created", $actor_id); }
			}
		}

		/**
		 * The stored path of a site's trunk page — what the front end prefixes a
		 * request with (BIGTREE_SITE_PATH) before looking it up in bigtree_pages and
		 * bigtree_route_history. "" for the primary site and for a single-site install.
		 *
		 * Read from the config rather than BigTreeCMS::$SiteRoots, which is only
		 * populated once a BigTreeCMS has been constructed — true of a front-end
		 * request, not of every API bootstrap.
		 */
		private static function sitePathFor(?string $site_key): string {
			global $bigtree;

			if ($site_key === null || $site_key === "") {

				return "";
			}

			$trunk = (int)($bigtree["config"]["sites"][$site_key]["trunk"] ?? 0);

			if ($trunk < 1) {

				return "";
			}

			return trim((string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $trunk), "/");
		}

		/**
		 * The 404 log, for the assistant.
		 *
		 * The read half of a workflow whose write half shipped first: create_redirect
		 * is level 1, `GET /404s` is level 1, and every natural phrasing of the ask
		 * ("what's 404ing since the relaunch?", "fix our broken links") starts at the
		 * log. Filtered exactly as list() is, through the same buildConditions, so the
		 * bucket the model asks for is the bucket the dashboard shows.
		 *
		 * `redirect_url` is stored through autoIPL, so it is decoded on the way out for
		 * the reason every AI read decodes a link: the model is being shown a value it
		 * may hand back to create_redirect.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiList404s(array $args, int $limit, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can read the 404 log."];
			}

			$type = trim((string)($args["type"] ?? "")) ?: "404";

			if (!in_array($type, ["404", "301", "ignored"], true)) {

				return ["error" => "\"{$type}\" isn't a 404 log bucket. Use \"404\" for unresolved broken URLs, "
					. "\"301\" for URLs that already redirect, or \"ignored\" for ones somebody dismissed."];
			}

			$site_key = trim((string)($args["site_key"] ?? ""));
			$q = trim((string)($args["q"] ?? ""));

			[$where, $bind] = $this->buildConditions($type, $site_key !== "" ? $site_key : null, $q);

			$limit = max(1, min($limit, self::AI_404_LIMIT));
			$total = (int)SQL::fetchSingle(...array_merge(
				["SELECT COUNT(*) FROM bigtree_404s" . $where], $bind
			));
			// Ordered by request volume, like the dashboard: the most-hit broken URL is
			// the one worth redirecting first, so a capped read returns the rows that
			// matter rather than an arbitrary slice.
			$rows = SQL::fetchAll(...array_merge([
				"SELECT * FROM bigtree_404s" . $where . " ORDER BY requests DESC, id DESC LIMIT " . (int)$limit,
			], $bind));

			$entries = [];

			foreach ($rows as $row) {
				$entry = $this->present($row);
				$entry["redirect_url"] = LinkService::untokenize((string)$entry["redirect_url"]);
				$entries[] = $entry;
			}

			$entries = PayloadBudget::fitRows($entries);

			return [
				"type" => $type,
				"total" => $total,
				"returned" => count($entries),
				"has_more" => $total > count($entries),
				"entries" => $entries,
			];
		}

		// — AI tool seam (RedirectToolBackend) —
		//
		// "Redirect /old-pricing to /pricing" is a routine editor ask that had no tool
		// and no decline line, so the model rediscovered the wall by failing. The
		// write is complete by construction — a source and a destination is the whole
		// record — and goes through create301 so the source is parsed and the
		// destination converted to an internal-page link exactly as the REST route
		// does. Administrator-only, matching the admin UI's own placement under
		// Developer → 404s.

		/**
		 * Validate a proposed 301 redirect without writing.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRedirectCreate(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can create redirects."];
			}

			$from = trim((string)($args["from"] ?? ""));
			$to = trim((string)($args["to"] ?? ""));

			if ($from === "" || $to === "") {

				return ["error" => "Both `from` (the old path that should redirect) and `to` (where it should go) "
					. "are required."];
			}

			$resolved_key = $this->aiResolveSiteKey($from, trim((string)($args["site_key"] ?? "")));

			if (isset($resolved_key["needs_input"])) {

				return $resolved_key;
			}

			$site_key = $resolved_key["site_key"];
			$parsed = self::parse404SourceURL($from, $site_key);
			$source = (string)$parsed["url"];

			// The site root strips to nothing, and a redirect from "" would capture
			// every unmatched request on the site.
			if ($source === "") {

				return ["error" => "\"{$from}\" doesn't name a path that can be redirected. Give the old path as it "
					. "appeared on the site (e.g. \"/old-pricing\" or a full URL)."];
			}

			$destination = $this->aiCheckRedirectDestination($to, $parsed);

			if (isset($destination["error"])) {

				return $destination;
			}

			$source_state = $this->aiRouteState($source, $parsed["site_key"], (string)$parsed["get_vars"]);
			$live = $this->aiRedirectSourceIsLive($source_state);

			if ($live !== null) {

				return ["error" => $live];
			}

			$source_notes = $this->aiRedirectSourceNotes($source_state);

			$existing = self::getExisting404($source, $parsed["get_vars"], $parsed["site_key"]);
			$previous = $existing ? (string)($existing["redirect_url"] ?? "") : "";
			// The stored destination, which set404Redirect writes through autoIPL — so a
			// redirect pointing at a page on this site is stored as `ipl://<base64>` (or
			// `{wwwroot}…`), and both the row and the summary sentence told the approver
			// they were replacing a base64 blob. `$previous` itself stays
			// stored-shaped: it is what the emptiness check below reads.
			$previous_display = PreviewValue::forHuman($previous);

			$preview = [
				"action" => "create_redirect",
				// Two plain rows rather than the top-level from/to pair ProposalCard
				// renders as one before/after: `from` here is the *source path*, not a
				// previous value, so the card struck it through and read "this path is
				// being deleted" — on the one card in the set that is about creating
				// something. The value genuinely being replaced is
				// `replaces`, which has always been its own row.
				"source" => "/" . $source,
				"destination" => $to,
				// On a multi-site install the redirect only fires for one site, and
				// the card never said which — so an approver on a five-site install
				// could not tell what they were approving.
				"site" => $site_key !== null ? (string)$site_key : "",
				"replaces" => $previous_display,
				"mode" => "published",
			];

			// An automatic route-history redirect at the source is checked by the
			// router before any 404 row, and create301 deletes it, so it is as much
			// a thing being replaced as an earlier 301.
			if ($source_notes["replaces"] !== "") {
				$preview["replaces"] = implode("; ", array_filter([$previous_display, $source_notes["replaces"]]));
			}

			// Not a refusal: a destination can legitimately be a module route, a
			// scheduled page or a path served outside BigTree. Surfacing it lets the
			// human approving the proposal catch a typo the shape check can't.
			$warning = trim(implode(" ", array_merge($source_notes["warnings"], [$destination["warning"]])));

			if ($warning !== "") {
				$preview["warning"] = $warning;
			}

			$result = [
				"ok" => true,
				"summary" => "Redirect /{$source} to {$to}"
					. ($site_key !== null ? " on the “{$site_key}” site." : ".")
					. ($previous !== ""
						? " This replaces the existing redirect to {$previous_display}."
						: ($existing
							? " This URL is already in the 404 log; it becomes a 301 redirect."
							: ""))
					. ($source_notes["replaces"] !== ""
						? " This replaces the " . $source_notes["replaces"] . "."
						: "")
					. ($source_notes["warnings"] ? " " . implode(" ", $source_notes["warnings"]) : ""),
				"preview" => $preview,
				"payload" => [
					"from" => $from,
					"to" => $to,
					"site_key" => $site_key,
				],
			];

			// Carried through so the staging layer can link this card to the pending
			// proposal that would create the destination page — see
			// aiCheckRedirectDestination and AbstractMutatingTool::resolvePriorChange.
			if (isset($destination["prior_change"])) {
				$result["prior_change"] = $destination["prior_change"];
			}

			return $result;
		}

		/**
		 * Resolve which site a redirect belongs to.
		 *
		 * On a multi-site install `site_key` is not optional in practice: the front-end
		 * 404 handler requires an exact match when BIGTREE_SITE_KEY is defined
		 * (cms.php), so a row stored with site_key NULL never fires — and the assistant
		 * reported "created" regardless. parse404SourceURL only auto-detects the key
		 * when it is handed a non-null one, so a full URL is matched against the
		 * configured domains here; anything else asks, because the model has no other
		 * way to discover the keys (`pages/sites` has no tool).
		 *
		 * A single-site install always resolves to null and never asks.
		 *
		 * @return array{site_key?:string|null,needs_input?:array<string,mixed>}
		 */
		private function aiResolveSiteKey(string $from, string $requested): array {
			global $bigtree;

			$sites = is_array($bigtree["config"]["sites"] ?? null) ? $bigtree["config"]["sites"] : [];

			if (!$sites) {

				return ["site_key" => null];
			}

			if ($requested !== "" && array_key_exists($requested, $sites)) {

				return ["site_key" => $requested];
			}

			if ($requested === "") {
				$host = parse_url($from, PHP_URL_HOST);

				if ($host) {
					foreach ($sites as $key => $site) {
						if (parse_url((string)($site["domain"] ?? ""), PHP_URL_HOST) === $host) {

							return ["site_key" => (string)$key];
						}
					}
				}
			}

			$options = [];

			foreach ($sites as $key => $site) {
				$options[] = [
					"id" => (string)$key,
					"label" => (string)($site["name"] ?? $key),
					"description" => (string)($site["domain"] ?? ""),
				];
			}

			return ["needs_input" => [
				"question" => $requested !== ""
					? "\"{$requested}\" isn't one of this install's sites. Which site is this redirect for?"
					: "This install serves more than one site. Which one is this redirect for?",
				"options" => $options,
			]];
		}

		/**
		 * Shape-, loop- and route-check a proposed redirect destination.
		 *
		 * REST is equally loose here, but a model guessing formats makes a malformed
		 * write far more likely, and this write goes live on approval with no pending
		 * queue and no human between: a bare phrase like "pricing page" would be
		 * served verbatim as a 301 Location, and from == to is an infinite loop the
		 * moment it's hit.
		 *
		 * An internal destination is followed the way a visitor's browser would follow
		 * it (aiRedirectChain), so a cycle through an existing redirect is refused as
		 * the one-hop self-loop is, and a chain is disclosed with where it really ends.
		 *
		 * @param array<string,mixed> $parsed The result of parse404SourceURL for `from`.
		 * @return array{error:string}|array{warning:string}
		 */
		private function aiCheckRedirectDestination(string $to, array $parsed): array {
			$is_absolute = preg_match('#^https?://#i', $to) === 1;
			$is_root_relative = substr($to, 0, 1) === "/";
			$is_ipl = strpos($to, "{wwwroot}") === 0 || strpos($to, "ipl://") === 0;

			if (!$is_absolute && !$is_root_relative && !$is_ipl) {

				return ["error" => "\"{$to}\" isn't a URL the browser can be sent to. Give the destination as a "
					. "site-relative path starting with \"/\" (e.g. \"/pricing\") or a full URL "
					. "(e.g. \"https://example.com/pricing\")."];
			}

			// parse_url returns null for a missing component and false for a URL it
			// can't parse at all — "https://" hits the second.
			if ($is_absolute && !parse_url($to, PHP_URL_HOST)) {

				return ["error" => "\"{$to}\" is not a valid URL — it has no domain."];
			}

			// A token is checked as the URL it decodes to, which is what the front end
			// sends the visitor to.
			$target = $is_ipl ? htmlspecialchars_decode(LinkService::untokenize($to)) : $to;

			// Normalize the destination the same way the source was so the comparison
			// is like for like: "/old", "old" and "https://site.com/old" all collapse
			// to the same stored path.
			$destination = self::parse404SourceURL($target, $parsed["site_key"]);

			if ($destination["url"] === $parsed["url"]
				&& $destination["get_vars"] === $parsed["get_vars"]
				&& $destination["site_key"] === $parsed["site_key"]
			) {

				return ["error" => "That redirect points at itself — \"{$to}\" resolves to the same path it's "
					. "redirecting from, which would loop forever. Give a different destination."];
			}

			// Only meaningful for internal destinations — an external URL is nothing
			// this install can check.
			if (!$this->aiIsInternalUrl($target)) {

				return ["warning" => ""];
			}

			$path = (string)$destination["url"];

			if ($path === "") {

				return ["warning" => ""];
			}

			$state = $this->aiRouteState($path, $destination["site_key"], (string)$destination["get_vars"]);
			$chain = $this->aiRedirectChain($state, $parsed);

			if (isset($chain["error"])) {

				return $chain;
			}

			$end = $chain["end"];
			$warnings = [];
			$prior = [];

			if ($chain["hops"]) {
				$count = count($chain["hops"]) + 1;
				$warnings[] = implode(" → ", $chain["hops"]) . " already redirects to {$chain["final"]}; visitors "
					. "will take {$count} hops — point at {$chain["final"]} directly?";
			}

			if ($end["page"] === null && $end["history"]["exact"] === null && $end["redirect"] === null) {
				$at = "/" . $end["path"];
				$held = $end["held_page"];

				if ($held !== null) {
					$warnings[] = "The page at {$at} (“{$held["title"]}”) " . $this->aiHeldPageClause($held);
				} elseif ($end === $state) {
					$warnings[] = "No page exists at {$at}. If that's a module route or a page that isn't published "
						. "yet this is fine — otherwise the redirect will land on a 404.";
					// "Create the new pricing page and point /old-pricing at it" stages
					// both, and this is the dangerous half: it looks like it worked, and
					// whether it lands on a real page depends on which card is approved
					// first. Non-blocking — a redirect to a page that doesn't exist yet
					// is legitimate — so it is recorded as a dependency rather than
					// refused. The value is the stored page path, trunk
					// included, because that is what a create_page card's `path` holds.
					$prior = ["prior_change" => [
						"tool" => "create_page",
						"value" => $state["full_path"],
						"keys" => ["path", "route"],
						"label" => "The page at {$at}",
						"blocking" => false,
					]];
				} else {
					$warnings[] = "No page exists at {$at}, where that chain ends, so the redirect will land on a 404.";
				}
			}

			return array_merge(["warning" => implode(" ", $warnings)], $prior);
		}

		/**
		 * Whether a URL is served by this install: a site-relative path, or a full URL
		 * on WWW_ROOT or on any configured site's domain.
		 */
		private function aiIsInternalUrl(string $url): bool {
			global $bigtree;

			if (preg_match('#^https?://#i', $url) !== 1 || strpos($url, WWW_ROOT) === 0) {

				return true;
			}

			$host = parse_url($url, PHP_URL_HOST);
			$sites = is_array($bigtree["config"]["sites"] ?? null) ? $bigtree["config"]["sites"] : [];

			foreach ($sites as $site) {
				if ($host && parse_url((string)($site["domain"] ?? ""), PHP_URL_HOST) === $host) {

					return true;
				}
			}

			return false;
		}

		/**
		 * Follow a redirect destination the way a visitor's browser would.
		 *
		 * Each hop is answered by aiRouteState: a live page or an automatic
		 * route-history redirect ends the chain, an existing 301 is another hop, and
		 * anything else is where the visitor lands. Walks at most the depth
		 * checkOldRoutes allows itself. A hop back to the source — or to any URL
		 * already on the chain — is a loop and is refused in the self-loop's words.
		 *
		 * @param array<string,mixed> $state aiRouteState for the destination.
		 * @param array<string,mixed> $parsed parse404SourceURL for the source.
		 * @return array{error:string}|array{hops:list<string>,final:string,end:array<string,mixed>}
		 */
		private function aiRedirectChain(array $state, array $parsed): array {
			$source = "/" . (string)$parsed["url"];
			$visited = [
				self::aiChainKey((string)$parsed["url"], $parsed["site_key"], (string)$parsed["get_vars"]),
				self::aiChainKey($state["path"], $state["site_key"], $state["get_vars"]),
			];
			$hops = [];
			$current = $state;

			for ($depth = 0; $depth < self::AI_REDIRECT_CHAIN_DEPTH; $depth++) {
				if ($current["page"] !== null) {

					return ["hops" => $hops, "final" => "/" . $current["path"], "end" => $current];
				}

				if ($current["history"]["exact"] !== null) {

					return [
						"hops" => array_merge($hops, ["/" . $current["path"]]),
						"final" => $current["history"]["exact"]["new_route"],
						"end" => $current,
					];
				}

				if ($current["redirect"] === null) {

					return ["hops" => $hops, "final" => "/" . $current["path"], "end" => $current];
				}

				$hops[] = "/" . $current["path"];
				$next_url = $current["redirect"]["url"];

				if (!$this->aiIsInternalUrl($next_url)) {

					return ["hops" => $hops, "final" => $next_url, "end" => ["page" => true] + $current];
				}

				$next = self::parse404SourceURL($next_url, $current["site_key"]);
				$key = self::aiChainKey((string)$next["url"], $next["site_key"], (string)$next["get_vars"]);

				if (in_array($key, $visited, true)) {
					$chain = implode(" → ", array_merge($hops, ["/" . (string)$next["url"]]));

					return ["error" => "That redirect would loop forever — " . $chain . " already "
						. ($key === $visited[0] ? "leads back to {$source}" : "loops back on itself")
						. ", so visitors would bounce between them. Give a different destination, or point the "
						. "existing redirect on {$hops[0]} somewhere else first."];
				}

				$visited[] = $key;
				$current = $this->aiRouteState((string)$next["url"], $next["site_key"], (string)$next["get_vars"]);
			}

			return ["hops" => $hops, "final" => "/" . $current["path"], "end" => ["page" => true] + $current];
		}

		/** One comparable identity for a URL on the chain: site, path and query. */
		private static function aiChainKey(string $path, ?string $site_key, string $get_vars): string {
			return (string)$site_key . "\0" . trim($path, "/") . "\0" . $get_vars;
		}

		/**
		 * What the front end would do with a request for $path on $site_key today.
		 *
		 * The one copy of the router's rules on the AI side. router.php
		 * tries three things in order — the page lookup (getNavId: trunk path prefixed,
		 * not archived, published, not expired), then checkOldRoutes over
		 * bigtree_route_history, then handle404 over bigtree_404s — and every AI check
		 * that asks "will this redirect fire?" or "does this URL lead anywhere?" reads
		 * the answer from here. Any query of bigtree_pages by path on the redirect
		 * seams belongs in this method (AISurfaceGuardTest sweeps for it).
		 *
		 * Deliberately not getPageIDForPath: that takes no site, and its routed-template
		 * prefix walk reports every path under a routed page as live, while a routed
		 * template that doesn't recognize a path hands it back to catch404 — so a
		 * redirect from under a routed page does fire.
		 *
		 * @return array{
		 *     path:string, site_key:?string, get_vars:string, full_path:string,
		 *     page:?array{id:int,title:string},
		 *     held_page:?array{id:int,title:string,reason:string,publish_at:string,expire_at:string},
		 *     history:array{exact:?array{new_route:string},prefix:?array{old_route:string,new_route:string,routed:bool}},
		 *     redirect:?array{id:int,url:string}
		 * }
		 */
		private function aiRouteState(string $path, ?string $site_key, string $get_vars = ""): array {
			$path = trim($path, "/");
			$site_path = self::sitePathFor($site_key);
			$full_path = trim($site_path . "/" . $path, "/");
			$state = [
				"path" => $path,
				"site_key" => $site_key,
				"get_vars" => $get_vars,
				"full_path" => $full_path,
				"page" => null,
				"held_page" => null,
				"history" => ["exact" => null, "prefix" => null],
				"redirect" => null,
			];

			// The page lookup, with getNavId's own clause evaluated on the database's
			// clock, as the router does.
			$rows = SQL::fetchAll(
				"SELECT id, nav_title, archived, publish_at, expire_at,
					(publish_at IS NULL OR publish_at <= NOW()) AS published,
					(expire_at IS NULL OR expire_at >= NOW()) AS unexpired
				FROM bigtree_pages WHERE path = ?",
				$full_path
			);

			foreach ($rows as $row) {
				$page = [
					"id" => (int)$row["id"],
					"title" => trim((string)$row["nav_title"]) ?: "page #" . (int)$row["id"],
				];
				$archived = (string)$row["archived"] !== "";

				if (!$archived && !empty($row["published"]) && !empty($row["unexpired"])) {
					$state["page"] = $page;
					$state["held_page"] = null;

					break;
				}

				if ($state["held_page"] === null) {
					$state["held_page"] = $page + [
						"reason" => $archived ? "archived" : (empty($row["published"]) ? "scheduled" : "expired"),
						"publish_at" => (string)($row["publish_at"] ?? ""),
						"expire_at" => (string)($row["expire_at"] ?? ""),
					];
				}
			}

			// checkOldRoutes: the longest matching old route wins. An exact match
			// redirects to wherever its chain leads; a shorter one only when the page
			// it leads to is on a routed template, otherwise the router falls through
			// to handle404. create301 deletes the exact row, so the prefix match is
			// looked for independently — it is what is left after the redirect lands.
			$segments = $full_path === "" ? [] : explode("/", $full_path);

			for ($x = count($segments); $x > 0; $x--) {
				$old_route = implode("/", array_slice($segments, 0, $x));
				$new_route = SQL::fetchSingle("SELECT new_route FROM bigtree_route_history WHERE old_route = ?", $old_route);

				if (!$new_route) {
					continue;
				}

				$target = $this->aiRouteHistoryTarget((string)$new_route);

				if ($x === count($segments)) {
					if ($target !== null) {
						$state["history"]["exact"] = ["new_route" => "/" . self::aiSiteRelative($target["path"], $site_path)];
					}

					continue;
				}

				$template = $target !== null ? \BigTreeJSONDB::get("templates", $target["template"]) : null;
				$state["history"]["prefix"] = [
					"old_route" => "/" . self::aiSiteRelative($old_route, $site_path),
					"new_route" => "/" . self::aiSiteRelative((string)($target["path"] ?? $new_route), $site_path),
					"routed" => $target !== null && !empty($template["routed"]),
				];

				break;
			}

			// handle404: an exact query-string match first, then the bare path's redirect.
			$row = self::getExisting404($path, $get_vars, $site_key);

			if ((!$row || (string)$row["redirect_url"] === "") && $get_vars !== "") {
				$row = self::getExisting404($path, "", $site_key);
			}

			if ($row && (string)$row["redirect_url"] !== "") {
				$state["redirect"] = [
					"id" => (int)$row["id"],
					"url" => htmlspecialchars_decode(LinkService::untokenize((string)$row["redirect_url"])),
				];
			}

			return $state;
		}

		/**
		 * The page a route-history entry leads to, following a page that moved more
		 * than once exactly as checkOldRoutes does (no archive or publish clause, the
		 * same depth). Part of aiRouteState.
		 *
		 * @return array{id:int,template:string,path:string}|null
		 */
		private function aiRouteHistoryTarget(string $route): ?array {
			$page = SQL::fetch("SELECT id, template, path FROM bigtree_pages WHERE path = ?", $route);
			$depth = self::AI_REDIRECT_CHAIN_DEPTH;

			while (!$page && $depth--) {
				$route = SQL::fetchSingle("SELECT new_route FROM bigtree_route_history WHERE old_route = ?", $route);

				if (!$route) {

					return null;
				}

				$page = SQL::fetch("SELECT id, template, path FROM bigtree_pages WHERE path = ?", $route);
			}

			return $page ? ["id" => (int)$page["id"], "template" => (string)$page["template"], "path" => (string)$page["path"]] : null;
		}

		/** A stored page path as the site it belongs to addresses it. */
		private static function aiSiteRelative(string $full_path, string $site_path): string {
			$full_path = trim($full_path, "/");

			if ($site_path !== "" && strpos($full_path . "/", $site_path . "/") === 0) {

				return trim(substr($full_path, strlen($site_path)), "/");
			}

			return $full_path;
		}

		/** Why a held page doesn't serve its URL, and what that means for a redirect landing there. */
		private function aiHeldPageClause(array $held): string {
			if ($held["reason"] === "scheduled") {

				return "isn't live until " . self::aiDisplayDate($held["publish_at"])
					. ", so the redirect will land on a 404 until then.";
			}

			if ($held["reason"] === "expired") {

				return "expired on " . self::aiDisplayDate($held["expire_at"]) . ", so the redirect will land on a 404.";
			}

			return "is archived, so the redirect will land on a 404 until it's unarchived.";
		}

		private static function aiDisplayDate(string $datetime): string {
			$time = strtotime($datetime);

			return $time ? date("F j, Y g:ia", $time) : $datetime;
		}

		/**
		 * Refuse a redirect whose source is a page that still resolves.
		 *
		 * handle404 only runs *after* routing fails (cms.php:1632), so a redirect from
		 * a live page's own path can never fire — it is a row that looks like it did
		 * something and never will. Says how to actually move the page instead.
		 *
		 * "Live" is the router's answer from aiRouteState: the page on *this* site
		 * (trunk path prefixed), not archived, published and not expired. An expired or
		 * scheduled page's URL 404s, so its redirect fires.
		 *
		 * Returns null when the source is genuinely unrouted.
		 *
		 * @param array<string,mixed> $state aiRouteState for the source.
		 */
		private function aiRedirectSourceIsLive(array $state): ?string {
			if ($state["page"] === null) {

				return null;
			}

			$site = $state["site_key"] !== null && $state["site_key"] !== "" ? " on the “{$state["site_key"]}” site" : "";

			return "/{$state["path"]} is a live page{$site} (“{$state["page"]["title"]}”), so a redirect from it would "
				. "never fire — BigTree only looks at redirects once a URL fails to route. To move that page's URL, "
				. "change its route with update_page or move it with move_page: either one leaves the redirect behind "
				. "automatically.";
		}

		/**
		 * What the source's route state means for the redirect being made there, beyond
		 * the liveness refusal: a scheduled page that will take the URL back (D2), an
		 * automatic route-history redirect create301 will delete (D4), and an old-route
		 * prefix that will keep catching the URL first (D4).
		 *
		 * @param array<string,mixed> $state aiRouteState for the source.
		 * @return array{warnings:list<string>,replaces:string}
		 */
		private function aiRedirectSourceNotes(array $state): array {
			$warnings = [];
			$replaces = "";
			$held = $state["held_page"];

			if ($held !== null && $held["reason"] === "scheduled") {
				$warnings[] = "/{$state["path"]} belongs to “{$held["title"]}”, which is scheduled to go live on "
					. self::aiDisplayDate($held["publish_at"]) . "; the redirect works until then, after which the "
					. "page takes over.";
			}

			if ($state["history"]["exact"] !== null) {
				$replaces = "automatic redirect to {$state["history"]["exact"]["new_route"]} (left when that page moved)";
			}

			$prefix = $state["history"]["prefix"];

			if ($prefix !== null && $prefix["routed"]) {
				$warnings[] = "This path is under {$prefix["old_route"]}, which already redirects automatically to "
					. "{$prefix["new_route"]}'s routed page, so this redirect will never fire.";
			}

			return ["warnings" => $warnings, "replaces" => $replaces];
		}

		/**
		 * Execute an approved redirect. Re-checks administrator level and reuses
		 * create301, so the redirect is shaped exactly like one made in the admin.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateRedirect(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can create redirects.");
			}

			$from = trim((string)($payload["from"] ?? ""));
			$to = trim((string)($payload["to"] ?? ""));
			$site_key = ($payload["site_key"] ?? null) !== null ? (string)$payload["site_key"] : null;

			if ($from === "" || $to === "") {

				return ["mode" => "error", "message" => "That redirect can no longer be created."];
			}

			// The configured sites can change during the proposal's life, and a key
			// that no longer names one would write a row the front end never matches.
			$resolved_key = $this->aiResolveSiteKey($from, (string)$site_key);

			if (isset($resolved_key["needs_input"])) {

				return ["mode" => "error", "message" => "This install's sites have changed since this was proposed — "
					. "ask again."];
			}

			$site_key = $resolved_key["site_key"];

			// Re-check shape and self-reference at approval — the payload is stored
			// between staging and approval and is never trusted on the way back out.
			$approval_parsed = self::parse404SourceURL($from, $site_key);

			if ((string)$approval_parsed["url"] === "") {

				return ["mode" => "error", "message" => "That redirect's source is no longer a path that can be "
					. "redirected."];
			}

			$destination = $this->aiCheckRedirectDestination($to, $approval_parsed);

			if (isset($destination["error"])) {

				return ["mode" => "error", "message" => $destination["error"]];
			}

			// A page can be created at the source path inside the proposal's 24h life,
			// which would make the redirect dead on arrival.
			$source_state = $this->aiRouteState(
				(string)$approval_parsed["url"],
				$approval_parsed["site_key"],
				(string)$approval_parsed["get_vars"]
			);
			$live = $this->aiRedirectSourceIsLive($source_state);

			if ($live !== null) {

				return ["mode" => "error", "message" => $live];
			}

			$replaced = $this->aiRedirectSourceNotes($source_state)["replaces"];

			$actor_id = 0;

			if (is_object($user)) {
				$actor_id = (int)($user->id ?? 0);
			} elseif (is_array($user)) {
				$actor_id = (int)($user["id"] ?? 0);
			}

			self::create301($from, $to, $site_key, $actor_id ?: null);

			$row = self::getExisting404(
				$approval_parsed["url"],
				$approval_parsed["get_vars"],
				$approval_parsed["site_key"]
			);

			$result = [
				"mode" => "created",
				"id" => (int)($row["id"] ?? 0),
				"from" => "/" . (string)$approval_parsed["url"],
				"to" => (string)($row["redirect_url"] ?? $to),
			];

			if ($replaced !== "") {
				$result["note"] = "This replaced the " . $replaced . ".";
			}

			return $result;
		}
	}
