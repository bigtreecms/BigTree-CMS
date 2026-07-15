<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\TagToolBackend;
	use BigTreeCMS;
	use BigTree;
	use SQL;

	class TagService implements TagToolBackend {
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
				// Closure required: private method arrays fail the `callable` type
				// check when passed across class boundaries into Pagination.
				function ($row) {
					return $this->present($row);
				},
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

		// — AI tool seam (TagToolBackend) —
		//
		// The add_tags assistant tool tags a page: an administrator action (matching
		// can_manage_tags) that also requires edit access to the page. Missing tags are
		// created and linked into bigtree_tags_rel exactly as the page editor does.

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateAddTags(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can manage tags."];
			}

			$page_id = (int)($args["page_id"] ?? 0);

			if ($page_id < 1) {

				return ["error" => "A page id is required to add tags."];
			}

			$page = SQL::fetch("SELECT id, nav_title, title FROM bigtree_pages WHERE id = ?", $page_id);

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {

				return ["denied" => "You do not have permission to edit this page."];
			}

			$names = $this->aiNormalizeTagNames($args["tags"] ?? []);

			if (!$names) {

				return ["error" => "Provide one or more tags to add."];
			}

			// Which tags already exist vs. would be newly created — surfaced in the
			// preview so the user sees exactly what a new tag would introduce.
			$new = [];
			$existing = [];

			foreach ($names as $name) {
				if (SQL::fetch("SELECT id FROM bigtree_tags WHERE tag = ?", $name)) {
					$existing[] = $name;
				} else {
					$new[] = $name;
				}
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}";

			return [
				"ok" => true,
				"summary" => "Add " . count($names) . " tag(s) to page “{$title}”"
					. ($new ? " (" . count($new) . " new)." : "."),
				"preview" => [
					"action" => "add_tags",
					"page_id" => $page_id,
					"page_title" => $title,
					"tags" => $names,
					"new_tags" => $new,
					"existing_tags" => $existing,
				],
				"payload" => [
					"page_id" => $page_id,
					"tags" => $names,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiAddTags(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can manage tags.");
			}

			$page_id = (int)($payload["page_id"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {
				throw new AuthorizationException("Insufficient page permission to tag page (e required)");
			}

			if (!SQL::exists("bigtree_pages", $page_id)) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			$names = $this->aiNormalizeTagNames($payload["tags"] ?? []);
			$added = [];

			foreach ($names as $name) {
				$tag_id = $this->aiFindOrCreateTag($name);
				$already = SQL::fetchSingle(
					"SELECT id FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ? AND tag = ?",
					(string)$page_id,
					$tag_id
				);

				if ($already) {

					continue;
				}

				SQL::insert("bigtree_tags_rel", [
					"table" => "bigtree_pages",
					"entry" => (string)$page_id,
					"tag" => $tag_id,
				]);
				$this->recomputeUsage($tag_id);
				$added[] = $name;
			}

			return [
				"mode" => "tagged",
				"page_id" => $page_id,
				"added" => $added,
			];
		}

		/**
		 * Normalize the model's proposed tag names the same way create() does, dropping
		 * empties and duplicates while preserving order.
		 *
		 * @param mixed $tags
		 * @return list<string>
		 */
		private function aiNormalizeTagNames($tags): array {
			$out = [];

			foreach ((array)$tags as $tag) {
				$name = $this->normalize((string)$tag);

				if ($name !== "" && !in_array($name, $out, true)) {
					$out[] = $name;
				}
			}

			return $out;
		}

		/**
		 * Return the id of an existing tag (by normalized name) or create it, mirroring
		 * create(). $name is already normalized.
		 */
		private function aiFindOrCreateTag(string $name): int {
			$existing = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $name);

			if ($existing) {

				return (int)$existing;
			}

			return (int)SQL::insert("bigtree_tags", [
				"tag" => $name,
				"metaphone" => metaphone($name),
				"route" => $this->uniqueRoute(BigTreeCMS::urlify($name)),
				"usage_count" => 0,
			]);
		}
	}
