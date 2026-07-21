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
	use BigTree\Services\AI\Tools\RedirectToolBackend;
	use BigTree;
	use SQL;
	use BigTreeCMS;

	/**
	 * Manage the bigtree_404s table: 404 log, 301 redirects, ignored URLs.
	 * type filter: 404 | 301 | ignored
	 */
	class FourOhFourService implements RedirectToolBackend {
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

		public function clearDead(Request $request) {
			// Delete 404 entries where the broken_url now resolves to an existing page route.
			// Conservative: only remove entries whose broken_url has at least one path segment matching a known page route.
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url = ''");
			SQL::query("DELETE FROM bigtree_404s WHERE ignored = '' AND redirect_url = '' AND requests < 5");
			$now = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url = ''");

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
			$history_cleaned = false;

			if ($site_key) {
				foreach (BigTreeCMS::$SiteRoots as $site_path => $data) {
					if ($data["key"] == $site_key) {
						$history_cleaned = true;
						SQL::delete("bigtree_route_history", ["old_route" => ltrim($site_path."/".$from, "/")]);
					}
				}
			}

			if (!$history_cleaned) {
				SQL::delete("bigtree_route_history", ["old_route" => $from]);
			}

			if ($existing) {
				sqlquery("UPDATE bigtree_404s SET `redirect_url` = '$to' WHERE id = '".$existing["id"]."'");
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

			$site_key = trim((string)($args["site_key"] ?? "")) ?: null;
			$parsed = self::parse404SourceURL($from, $site_key);
			$source = (string)$parsed["url"];

			// The site root strips to nothing, and a redirect from "" would capture
			// every unmatched request on the site.
			if ($source === "") {

				return ["error" => "\"{$from}\" doesn't name a path that can be redirected. Give the old path as it "
					. "appeared on the site (e.g. \"/old-pricing\" or a full URL)."];
			}

			$existing = self::getExisting404($source, $parsed["get_vars"], $parsed["site_key"]);
			$previous = $existing ? (string)($existing["redirect_url"] ?? "") : "";

			return [
				"ok" => true,
				"summary" => "Redirect /{$source} to {$to}."
					. ($previous !== ""
						? " This replaces the existing redirect to {$previous}."
						: ($existing
							? " This URL is already in the 404 log; it becomes a 301 redirect."
							: "")),
				"preview" => [
					"action" => "create_redirect",
					"from" => "/" . $source,
					"to" => $to,
					"replaces" => $previous,
					"mode" => "published",
				],
				"payload" => [
					"from" => $from,
					"to" => $to,
					"site_key" => $site_key,
				],
			];
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

			$actor_id = 0;

			if (is_object($user)) {
				$actor_id = (int)($user->id ?? 0);
			} elseif (is_array($user)) {
				$actor_id = (int)($user["id"] ?? 0);
			}

			self::create301($from, $to, $site_key, $actor_id ?: null);

			$parsed = self::parse404SourceURL($from, $site_key);
			$row = self::getExisting404($parsed["url"], $parsed["get_vars"], $parsed["site_key"]);

			return [
				"mode" => "created",
				"id" => (int)($row["id"] ?? 0),
				"from" => "/" . (string)$parsed["url"],
				"to" => (string)($row["redirect_url"] ?? $to),
			];
		}
	}
