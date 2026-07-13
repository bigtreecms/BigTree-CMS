<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeCMS;
	use BigTree;
	use SQL;

	class TagService {
		public function list(Request $request) {
			$q = $request->queryString("q");

			$where = "";
			$args = [];

			if ($q !== "") {
				$where = " WHERE tag LIKE ?";
				$args[] = Sanitize::likeTerm($q, true);
			}

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_tags" . $where,
				"SELECT id, tag, route, usage_count FROM bigtree_tags" . $where . " ORDER BY tag ASC",
				$args,
				[$this, "present"],
				100
			);
		}

		public function search(Request $request) {
			$q = $request->queryString("q");

			if ($q === "") {
				return Response::ok([]);
			}
			$rows = SQL::fetchAll(
				"SELECT id, tag, route, usage_count FROM bigtree_tags WHERE tag LIKE ? ORDER BY usage_count DESC LIMIT 25",
				Sanitize::likeTerm($q, true)
			);

			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_tags", $id, "Tag", "id, tag, route, usage_count");

			return Response::ok($this->present($row));
		}

		public function create(Request $request) {
			$tag = $this->normalize($request->bodyString("tag"));

			if ($tag === "") {
				throw new BadRequestException("Empty tag after normalization", "empty_tag");
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
			$id = $request->id();
			Entity::assertExists("bigtree_tags", $id, "Tag");

			SQL::delete("bigtree_tags", $id);
			SQL::query("DELETE FROM bigtree_tags_rel WHERE tag = ?", $id);

			return Response::noContent();
		}

		public function merge(Request $request) {
			$into = $request->bodyInt("into");
			$from = $request->bodyList("from", "int");

			if (!$into || !$from) {
				throw new BadRequestException("into and from required", "missing_fields");
			}

			if (in_array($into, $from, true)) {
				throw new BadRequestException("into cannot be in from list", "invalid_merge");
			}

			Entity::assertExists("bigtree_tags", $into, "Target tag");

			foreach ($from as $tag_id) {
				SQL::query("UPDATE bigtree_tags_rel SET tag = ? WHERE tag = ?", $into, $tag_id);
				SQL::delete("bigtree_tags", $tag_id);
			}

			$this->recomputeUsage($into);

			return Response::ok($this->present(SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $into)));
		}

		// — helpers —

		/**
		 * Canonical 4-key tag presenter. Public static so PageService::loadTags
		 * and SearchService::searchTags share one shape (id/tag/route/usage_count).
		 */
		public static function presentRow(array $row): array {

			return [
				"id" => (int)$row["id"],
				"tag" => $row["tag"],
				"route" => $row["route"],
				"usage_count" => (int)$row["usage_count"],
			];
		}

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

			return self::presentRow($row);
		}
	}
