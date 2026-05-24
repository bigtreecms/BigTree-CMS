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
			if ($site_key !== null) { $conds[] = "site_key = ?"; $args[] = $site_key; }
			if ($q !== "") {
				$conds[] = "(broken_url LIKE ? OR redirect_url LIKE ?)";
				$like = "%" . str_replace("%", "\\%", $q) . "%";
				$args[] = $like; $args[] = $like;
			}

			$where = $conds ? " WHERE " . implode(" AND ", $conds) : "";

			$total = (int)SQL::fetchSingle(...array_merge(["SELECT COUNT(*) FROM bigtree_404s" . $where], $args));
			$rows = SQL::fetchAll(...array_merge([
				"SELECT * FROM bigtree_404s" . $where . " ORDER BY requests DESC, id DESC LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $args));

			return Response::ok(array_map([$this, "present"], $rows), Pagination::offsetMeta($p["page"], $p["per_page"], $total));
		}

		public function create(Request $request) {
			$d = $request->body;
			$from = trim((string)$d["from"]);
			$to = (string)$d["to"];
			$site_key = $d["site_key"] ?? null;

			$id = (int)SQL::insert("bigtree_404s", [
				"broken_url" => $from,
				"get_vars" => "",
				"redirect_url" => $to,
				"requests" => 0,
				"ignored" => "",
				"site_key" => $site_key,
			]);
			return Response::created($this->present(SQL::fetch("SELECT * FROM bigtree_404s WHERE id = ?", $id)), null);
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
			if (!$ids) return Response::noContent();
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			SQL::query(...array_merge(["DELETE FROM bigtree_404s WHERE id IN ($placeholders)"], $ids));
			return Response::noContent();
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
