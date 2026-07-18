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
	use BigTreeJSONDB;
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
			// Match legacy BigTreeAdmin::createTag — keep spaces, strip other non-alnum.
			return strtolower(trim(preg_replace('/[^a-zA-Z0-9 ]/', '', $t)));
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
		// add_tags / remove_tags work on either a page or a module entry (both carry
		// tags through bigtree_tags_rel), and are linked exactly as the page editor
		// does. Permission is split the way the admin UI itself splits it: *attaching*
		// an existing tag needs only edit access on the thing being tagged, while
		// *creating* a tag — which grows the site's shared vocabulary — stays
		// administrator-only. Detaching never deletes the tag row itself.
		//
		// The target's table is always re-resolved from the module id, never taken
		// from the model or the stored payload, so a tag write can't be pointed at an
		// arbitrary table.

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateAddTags(array $args, $user): array {
			$target = $this->aiResolveTagTarget($args, $user);

			if (isset($target["error"]) || isset($target["denied"])) {

				return $target;
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

			// Attaching a tag that already exists is what the page editor lets any
			// editor do, so it stays at editor level. Coining a *new* tag grows the
			// site's shared vocabulary and stays administrator-only.
			if ($new && PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can create new tags. These don't exist yet: "
					. implode(", ", $new) . ". You can still add tags that already exist."];
			}

			$label = $target["label"];

			return [
				"ok" => true,
				"summary" => "Add " . count($names) . " tag(s) to “{$label}”"
					. ($new ? " (" . count($new) . " new)." : "."),
				"preview" => [
					"action" => "add_tags",
					"target" => $target["kind"],
					"target_title" => $label,
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : null,
					"module_id" => $target["module_id"],
					"entry_id" => $target["entry_id"],
					"tags" => $names,
					"new_tags" => $new,
					"existing_tags" => $existing,
				],
				"payload" => [
					"table" => $target["table"],
					"entry_id" => $target["entry_id"],
					"module_id" => $target["module_id"],
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : 0,
					"tags" => $names,
					"creates_tags" => $new,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiAddTags(array $payload, $user): array {
			$target = $this->aiReauthorizeTagTarget($payload, $user);

			if (isset($target["mode"])) {

				return $target;
			}

			$names = $this->aiNormalizeTagNames($payload["tags"] ?? []);

			// Re-check the create-vs-attach split at approval: a tag that was new at
			// staging may exist now (or vice versa), and only an admin may coin one.
			if (PermissionService::level($user) < 1) {
				foreach ($names as $name) {
					if (!SQL::fetch("SELECT id FROM bigtree_tags WHERE tag = ?", $name)) {
						throw new AuthorizationException("Only administrators can create new tags");
					}
				}
			}

			$added = [];

			foreach ($names as $name) {
				$tag_id = $this->aiFindOrCreateTag($name);
				$already = SQL::fetchSingle(
					"SELECT id FROM bigtree_tags_rel WHERE `table` = ? AND entry = ? AND tag = ?",
					$target["table"],
					(string)$target["entry_id"],
					$tag_id
				);

				if ($already) {

					continue;
				}

				SQL::insert("bigtree_tags_rel", [
					"table" => $target["table"],
					"entry" => (string)$target["entry_id"],
					"tag" => $tag_id,
				]);
				$this->recomputeUsage($tag_id);
				$added[] = $name;
			}

			return [
				"mode" => "tagged",
				"table" => $target["table"],
				"entry_id" => $target["entry_id"],
				"page_id" => $target["table"] === "bigtree_pages" ? $target["entry_id"] : null,
				"added" => $added,
			];
		}

		/**
		 * Validate removing tags from a page or module entry. add_tags shipped without
		 * an inverse, so a tag the assistant added could only be taken off in the
		 * admin UI.
		 *
		 * Detaching never destroys the tag itself, so it needs no admin gate — only
		 * edit access on the thing being untagged.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRemoveTags(array $args, $user): array {
			$target = $this->aiResolveTagTarget($args, $user);

			if (isset($target["error"]) || isset($target["denied"])) {

				return $target;
			}

			$names = $this->aiNormalizeTagNames($args["tags"] ?? []);

			if (!$names) {

				return ["error" => "Provide one or more tags to remove."];
			}

			$attached = [];
			$not_attached = [];

			foreach ($names as $name) {
				$tag_id = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $name);
				$has = $tag_id && SQL::fetchSingle(
					"SELECT id FROM bigtree_tags_rel WHERE `table` = ? AND entry = ? AND tag = ?",
					$target["table"], (string)$target["entry_id"], (int)$tag_id
				);

				if ($has) {
					$attached[] = $name;
				} else {
					$not_attached[] = $name;
				}
			}

			if (!$attached) {

				return ["error" => "None of those tags are on this " . $target["kind"] . ": "
					. implode(", ", $not_attached) . "."];
			}

			$label = $target["label"];
			$note = $not_attached
				? " (" . implode(", ", $not_attached) . " " . (count($not_attached) === 1 ? "isn't" : "aren't")
					. " on it and will be ignored)"
				: "";

			return [
				"ok" => true,
				"summary" => "Remove " . count($attached) . " tag(s) from “{$label}”" . $note
					. ". The tags themselves are not deleted.",
				"preview" => [
					"action" => "remove_tags",
					"target" => $target["kind"],
					"target_title" => $label,
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : null,
					"module_id" => $target["module_id"],
					"entry_id" => $target["entry_id"],
					"tags" => $attached,
					"ignored" => $not_attached,
				],
				"payload" => [
					"table" => $target["table"],
					"entry_id" => $target["entry_id"],
					"module_id" => $target["module_id"],
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : 0,
					"tags" => $attached,
				],
			];
		}

		/**
		 * Execute an approved tag removal. Re-checks edit access on the target.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiRemoveTags(array $payload, $user): array {
			$target = $this->aiReauthorizeTagTarget($payload, $user);

			if (isset($target["mode"])) {

				return $target;
			}

			$removed = [];

			foreach ($this->aiNormalizeTagNames($payload["tags"] ?? []) as $name) {
				$tag_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $name);

				if (!$tag_id) {

					continue;
				}

				SQL::query(
					"DELETE FROM bigtree_tags_rel WHERE `table` = ? AND entry = ? AND tag = ?",
					$target["table"], (string)$target["entry_id"], $tag_id
				);

				// The tag row itself is deliberately left in place — other content may
				// still use it, and deleting tags is not an assistant action.
				$this->recomputeUsage($tag_id);
				$removed[] = $name;
			}

			return [
				"mode" => "untagged",
				"table" => $target["table"],
				"entry_id" => $target["entry_id"],
				"page_id" => $target["table"] === "bigtree_pages" ? $target["entry_id"] : null,
				"removed" => $removed,
			];
		}

		/**
		 * Resolve what is being tagged — a page (`page_id`) or a module entry
		 * (`module_id` + `entry_id`) — and enforce edit access on it.
		 *
		 * Tagging was pages-only, even though module entries carry tags through the
		 * same `__tags__` write path. The module's own form table is what the relation
		 * is keyed on, so it's resolved from the module rather than taken from the
		 * model — a caller-supplied table would let tags be written against any table.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function aiResolveTagTarget(array $args, $user): array {
			$page_id = (int)($args["page_id"] ?? 0);
			$module_id = trim((string)($args["module_id"] ?? ""));
			$entry_id = (int)($args["entry_id"] ?? 0);

			if ($page_id > 0 && $module_id !== "") {

				return ["error" => "Provide either a page_id or a module_id + entry_id, not both."];
			}

			if ($page_id > 0) {
				$page = SQL::fetch("SELECT id, nav_title, title FROM bigtree_pages WHERE id = ?", $page_id);

				if (!$page) {

					return ["error" => "Page {$page_id} does not exist."];
				}

				if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {

					return ["denied" => "You do not have permission to edit this page."];
				}

				return [
					"kind" => "page",
					"table" => "bigtree_pages",
					"entry_id" => $page_id,
					"module_id" => "",
					"label" => trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}",
				];
			}

			if ($module_id === "" || $entry_id < 1) {

				return ["error" => "Provide either a page_id, or a module_id and entry_id, to identify what to tag."];
			}

			$module = BigTreeJSONDB::get("modules", $module_id) ?: BigTreeJSONDB::get("modules", $module_id, "route");

			if (!$module) {

				return ["error" => "Module \"{$module_id}\" does not exist."];
			}

			$forms = is_array($module["forms"] ?? null) ? $module["forms"] : [];
			$table = (string)($forms[0]["table"] ?? $module["table"] ?? "");

			if ($table === "") {

				return ["error" => "This module has no entry table, so its entries can't be tagged."];
			}

			if (!PermissionService::userHasModuleAccess($user, (string)$module["id"], "e")) {

				return ["denied" => "You do not have permission to edit entries in this module."];
			}

			$row = SQL::fetch("SELECT * FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", $entry_id);

			if (!$row) {

				return ["error" => "Entry {$entry_id} does not exist in this module."];
			}

			if (PermissionService::userRowLevel($user, $module, $row) === "n") {

				return ["denied" => "You do not have permission to edit this specific entry."];
			}

			$label = "";

			foreach (["title", "name", "headline", "nav_title"] as $column) {
				$label = trim((string)($row[$column] ?? ""));

				if ($label !== "") {

					break;
				}
			}

			return [
				"kind" => "entry",
				"table" => $table,
				"entry_id" => $entry_id,
				"module_id" => (string)$module["id"],
				"label" => $label !== "" ? $label : "entry #{$entry_id}",
			];
		}

		/**
		 * Re-derive and re-authorize a stored tag payload's target at approval time.
		 * The stored `table` is never trusted — it's re-resolved from the module id so
		 * a tampered payload can't redirect the write at another table.
		 *
		 * Returns the resolved target, or a ["mode" => "error"] result to hand back.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function aiReauthorizeTagTarget(array $payload, $user): array {
			$args = [
				"page_id" => (int)($payload["page_id"] ?? 0),
				"module_id" => (string)($payload["module_id"] ?? ""),
				"entry_id" => (int)($payload["entry_id"] ?? 0),
			];

			// A page payload carries page_id == entry_id; don't send both through.
			if ($args["page_id"] > 0) {
				$args["module_id"] = "";
				$args["entry_id"] = 0;
			}

			$target = $this->aiResolveTagTarget($args, $user);

			if (isset($target["denied"])) {
				throw new AuthorizationException((string)$target["denied"]);
			}

			if (isset($target["error"])) {

				return ["mode" => "error", "message" => (string)$target["error"]];
			}

			return $target;
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
