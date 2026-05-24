<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTree;
	use SQL;

	class TagService {
		public function list(Request $request) {
			$p = Pagination::offset($request, 100);
			$q = trim((string)($request->query["q"] ?? ""));

			$where = "";
			$args = [];

			if ($q !== "") {
				$where = " WHERE tag LIKE ?";
				$args[] = "%" . str_replace("%", "\\%", strtolower($q)) . "%";
			}

			$total = (int)SQL::fetchSingle(...array_merge(["SELECT COUNT(*) FROM bigtree_tags" . $where], $args));
			$rows = SQL::fetchAll(...array_merge([
				"SELECT id, tag, route, usage_count FROM bigtree_tags" . $where . " ORDER BY tag ASC LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $args));

			$items = array_map([$this, "present"], $rows);

			return Response::ok($items, Pagination::offsetMeta($p["page"], $p["per_page"], $total));
		}

		public function search(Request $request) {
			$q = trim((string)($request->query["q"] ?? ""));

			if ($q === "") {
				return Response::ok([]);
			}
			$rows = SQL::fetchAll(
				"SELECT id, tag, route, usage_count FROM bigtree_tags WHERE tag LIKE ? ORDER BY usage_count DESC LIMIT 25",
				"%" . str_replace("%", "\\%", strtolower($q)) . "%"
			);

			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$row = SQL::fetch("SELECT id, tag, route, usage_count FROM bigtree_tags WHERE id = ?", $id);

			if (!$row) {
				throw new NotFoundException("Tag $id not found", "resource_not_found", 404);
			}
			return Response::ok($this->present($row));
		}

		public function create(Request $request) {
			$tag = $this->normalize((string)$request->body["tag"]);

			if ($tag === "") {
				throw new BadRequestException("Empty tag after normalization", "empty_tag", 400);
			}

			$existing = SQL::fetch("SELECT id, tag, route, usage_count FROM bigtree_tags WHERE tag = ?", $tag);

			if ($existing) {
				return Response::ok($this->present($existing));
			}

			$route = $this->uniqueRoute(BigTreeCMS::urlify($tag));
			$id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag,
				"metaphone" => metaphone($tag),
				"route" => $route,
				"usage_count" => 0,
			]);
			$row = SQL::fetch("SELECT id, tag, route, usage_count FROM bigtree_tags WHERE id = ?", $id);

			return Response::created($this->present($row), null);
		}

		public function delete(Request $request) {
			$id = (int)$request->route_params["id"];

			if (!SQL::exists("bigtree_tags", $id)) {
				throw new NotFoundException("Tag $id not found", "resource_not_found", 404);
			}

			SQL::delete("bigtree_tags", $id);
			SQL::query("DELETE FROM bigtree_tags_rel WHERE tag = ?", $id);

			return Response::noContent();
		}

		public function merge(Request $request) {
			$into = (int)$request->body["into"];
			$from = array_map("intval", (array)$request->body["from"]);

			if (!$into || !$from) {
				throw new BadRequestException("into and from required", "missing_fields", 400);
			}

			if (in_array($into, $from, true)) {
				throw new BadRequestException("into cannot be in from list", "invalid_merge", 400);
			}

			if (!SQL::exists("bigtree_tags", $into)) {
				throw new NotFoundException("Target tag $into not found", "resource_not_found", 404);
			}

			foreach ($from as $tag_id) {
				SQL::query("UPDATE bigtree_tags_rel SET tag = ? WHERE tag = ?", $into, $tag_id);
				SQL::delete("bigtree_tags", $tag_id);
			}

			$this->recomputeUsage($into);

			return Response::ok($this->present(SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $into)));
		}

		// — helpers —

		private function normalize($t) {

			return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $t)));
		}

		private function uniqueRoute($base) {
			$route = $base;
			$x = 2;

			while (SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE route = ?", $route)) {
				$route = $base . "-" . $x++;
			}

			return $route;
		}

		private function recomputeUsage($id) {
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $id);
			SQL::update("bigtree_tags", $id, ["usage_count" => $count]);
		}

		private function present(array $row) {

			return [
				"id" => (int)$row["id"],
				"tag" => $row["tag"],
				"route" => $row["route"],
				"usage_count" => (int)$row["usage_count"],
			];
		}
	}
