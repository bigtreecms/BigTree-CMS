<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree;
	use SQL;

	/**
	 * Manage the bigtree_404s table: 404 log, 301 redirects, ignored URLs.
	 * type filter: 404 | 301 | ignored
	 */
	class FourOhFourService {
		public function list(Request $request) {
			$p = Pagination::offset($request, 100);
			$type = $request->query["type"] ?? "404";
			$site_key = $request->query["site_key"] ?? null;
			$q = trim((string)($request->query["q"] ?? ""));

			[$where, $args] = $this->buildConditions($type, $site_key, $q);

			$total = (int)SQL::fetchSingle(...array_merge(["SELECT COUNT(*) FROM bigtree_404s" . $where], $args));
			$rows = SQL::fetchAll(...array_merge([
				"SELECT * FROM bigtree_404s" . $where . " ORDER BY requests DESC, id DESC LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $args));

			return Response::ok(array_map([$this, "present"], $rows), Pagination::offsetMeta($p["page"], $p["per_page"], $total));
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

			$rows = SQL::fetchAll(...array_merge([
				"SELECT * FROM bigtree_404s" . $where . " ORDER BY requests DESC, id DESC",
			], $args));

			return Response::ok(array_map([$this, "present"], $rows));
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
					throw new BadRequestException("type must be 404, 301 or ignored", "bad_type", 400);
			}

			if ($site_key !== null) {
				$conds[] = "site_key = ?";
				$args[] = $site_key;
			}

			if ($q !== "") {
				$conds[] = "(broken_url LIKE ? OR redirect_url LIKE ?)";
				$like = "%" . str_replace("%", "\\%", $q) . "%";
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
			$id = (int)$request->route_params["id"];

			if (!SQL::exists("bigtree_404s", $id)) {
				throw new NotFoundException("404 $id not found", "resource_not_found", 404);
			}

			SQL::delete("bigtree_404s", $id);

			return Response::noContent();
		}

		public function setRedirect(Request $request) {
			$id = (int)$request->route_params["id"];

			if (!SQL::exists("bigtree_404s", $id)) {
				throw new NotFoundException("404 $id not found", "resource_not_found", 404);
			}

			SQL::update("bigtree_404s", $id, ["redirect_url" => (string)$request->body["url"], "ignored" => ""]);

			return Response::ok($this->present(SQL::fetch("SELECT * FROM bigtree_404s WHERE id = ?", $id)));
		}

		public function ignore(Request $request) {
			$id = (int)$request->route_params["id"];

			if (!SQL::exists("bigtree_404s", $id)) {
				throw new NotFoundException("404 $id not found", "resource_not_found", 404);
			}

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
			$ids = array_map("intval", (array)$request->body["ids"]);

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
			$files = $request->file("file");
			$file = is_array($files) ? ($files[0] ?? null) : null;

			if (
				!is_array($file)
				|| ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
				|| empty($file["tmp_name"])
			) {
				throw new BadRequestException("No CSV file was uploaded.", "missing_file", 400);
			}

			$handle = fopen($file["tmp_name"], "r");

			if ($handle === false) {
				throw new BadRequestException("Could not read the uploaded file.", "unreadable_file", 400);
			}

			$site_key = trim((string)($request->body["site_key"] ?? "")) ?: null;
			$first_row_titles = !empty($request->body["first_row_titles"]);
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
				"ignored" => $r["ignored"] === "on",
				"site_key" => $r["site_key"],
				"type" => $r["ignored"] === "on" ? "ignored" : ($r["redirect_url"] !== "" ? "301" : "404"),
			];
		}
	}
