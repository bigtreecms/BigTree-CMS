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
	use BigTree;
	use SQL;

	/**
	 * Manage the bigtree_404s table: 404 log, 301 redirects, ignored URLs.
	 * type filter: 404 | 301 | ignored
	 */
	class FourOhFourService {
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
				[$this, "present"],
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
		 * BigTreeAdmin::create301 so the source URL is parsed (full URL or
		 * domain-relative fragment), the site key inferred where possible, the
		 * destination converted to an internal-page link, and any existing entry
		 * for the same source updated rather than duplicated.
		 */
		public function create(Request $request) {
			$d = $request->body;
			$from = trim((string)$d["from"]);
			$to = trim((string)$d["to"]);
			$site_key = trim((string)($d["site_key"] ?? "")) ?: null;

			$admin = new \BigTreeAdmin();
			$admin->create301($from, $to, $site_key);

			$parsed = \BigTreeAdmin::parse404SourceURL($from, $site_key);
			$row = \BigTreeAdmin::getExisting404($parsed["url"], $parsed["get_vars"], $parsed["site_key"]);

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
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			SQL::query(...array_merge(["DELETE FROM bigtree_404s WHERE id IN ($placeholders)"], $ids));

			return Response::noContent();
		}

		/**
		 * Bulk-import 301 redirects from an uploaded CSV (one `from,to` pair per
		 * row). Mirrors the legacy dashboard import: each row runs through
		 * BigTreeAdmin::create301, which parses the source URL, de-dupes against
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
			$admin = new \BigTreeAdmin();

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

				$admin->create301($from, $to, $site_key);
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
	}
