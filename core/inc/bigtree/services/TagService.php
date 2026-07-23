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
				$this->retargetTagRelations($tag_id, $into);
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

		/**
		 * Point every relation on $from at $into, dropping the ones that would
		 * collide.
		 *
		 * A blind `UPDATE bigtree_tags_rel SET tag = ?` gives any record that carried
		 * *both* tags two identical relation rows: there is no unique key on
		 * (table, entry, tag), nothing that reads the relations uses DISTINCT, so the
		 * tag renders twice on the front end, and recomputeUsage counts rows rather
		 * than records so the usage count is permanently wrong. aiAddTags has always
		 * checked for the collision; merging has to as well.
		 */
		private function retargetTagRelations(int $from, int $into): void {
			SQL::query(
				"DELETE r FROM bigtree_tags_rel r
				 JOIN bigtree_tags_rel keep ON keep.`table` = r.`table` AND keep.entry = r.entry AND keep.tag = ?
				 WHERE r.tag = ?",
				$into,
				$from
			);
			SQL::query("UPDATE bigtree_tags_rel SET tag = ? WHERE tag = ?", $into, $from);
		}

		/**
		 * Point a merged-away tag id at its replacement inside every queued draft's
		 * `tags_changes` blob.
		 *
		 * A pending change stores tag *ids*, and publishing replays that blob. Merging
		 * a tag rewrote the live relations and deleted the row but left those blobs
		 * alone, so approving a draft afterwards re-attached an id that no longer
		 * exists — a tag relation pointing at nothing, invisible everywhere.
		 */
		private function retargetQueuedTagChanges(int $from, int $into): void {
			$rows = SQL::fetchAll(
				"SELECT id, tags_changes FROM bigtree_pending_changes WHERE tags_changes IS NOT NULL AND tags_changes != ''"
			);

			foreach ($rows as $row) {
				$tags = json_decode((string)$row["tags_changes"], true);

				if (!is_array($tags)) {

					continue;
				}

				$ids = array_map("intval", $tags);

				if (!in_array($from, $ids, true)) {

					continue;
				}

				$next = [];

				foreach ($ids as $id) {
					$id = $id === $from ? $into : $id;

					// The record may already carry the target tag; a merge must not
					// give it the same relation twice.
					if (!in_array($id, $next, true)) {
						$next[] = $id;
					}
				}

				SQL::update("bigtree_pending_changes", (int)$row["id"], ["tags_changes" => $next]);
			}
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
		 * Normalize a model-supplied tag list to canonical names. Public so other
		 * services staging a tag-bearing proposal (create_page) apply the same
		 * normalization the tag tools do rather than inventing their own.
		 *
		 * @param mixed $tags
		 * @return list<string>
		 */
		public function aiTagNames($tags): array {

			return $this->aiNormalizeTagNames($tags);
		}

		/**
		 * Which of these tag names don't exist yet. Coining a new tag grows the site's
		 * shared vocabulary and stays administrator-only, so callers gate on this.
		 *
		 * @param list<string> $names
		 * @return list<string>
		 */
		public function aiNewTagNames(array $names): array {
			$new = [];

			foreach ($names as $name) {
				if (!SQL::fetch("SELECT id FROM bigtree_tags WHERE tag = ?", $name)) {
					$new[] = $name;
				}
			}

			return $new;
		}

		/**
		 * Resolve tag names to ids, creating any that don't exist. Callers must have
		 * already gated new-tag creation on administrator level (see aiNewTagNames);
		 * this is the write half only.
		 *
		 * @param list<string> $names
		 * @return list<int>
		 */
		public function aiResolveTagIds(array $names): array {
			$ids = [];

			foreach ($names as $name) {
				$ids[] = $this->aiFindOrCreateTag($name);
			}

			return $ids;
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateAddTags(array $args, $user): array {
			$target = $this->aiResolveTagTarget($args, $user);

			// A multi-form module has no safe default table; hand the choice back so the
			// tool can ask which form rather than tagging against the wrong one.
			if (isset($target["error"]) || isset($target["denied"]) || !empty($target["ambiguous_form"])) {

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
			$can_publish = !empty($target["can_publish"]);

			return [
				"ok" => true,
				"summary" => "Add " . count($names) . " tag(s) to “{$label}”"
					. ($new ? " (" . count($new) . " new)." : ".")
					. $this->aiTagModeNote($can_publish, $target["kind"]),
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
					"mode" => $can_publish ? "published" : "pending",
				],
				"payload" => [
					"table" => $target["table"],
					"entry_id" => $target["entry_id"],
					"module_id" => $target["module_id"],
					"form" => (string)($target["form_id"] ?? ""),
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : 0,
					"tags" => $names,
					"creates_tags" => $new,
				],
				"fingerprint" => $this->aiTagFingerprint($target),
				"lock" => $this->aiTagLock($target),
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

			// A non-publisher's tag change is queued, exactly as their edit to any
			// other field of the same page or entry would be.
			if (empty($target["can_publish"])) {
				$ids = $this->aiCurrentTagIds($target);
				$added = [];

				foreach ($names as $name) {
					$tag_id = $this->aiFindOrCreateTag($name);

					if (in_array($tag_id, $ids, true)) {

						continue;
					}

					$ids[] = $tag_id;
					$added[] = $name;
				}

				return $this->aiQueueTagChange($target, $ids, $user, "added", $added);
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

			// A multi-form module has no safe default table; hand the choice back so the
			// tool can ask which form rather than tagging against the wrong one.
			if (isset($target["error"]) || isset($target["denied"]) || !empty($target["ambiguous_form"])) {

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
			$can_publish = !empty($target["can_publish"]);
			$note = $not_attached
				? " (" . implode(", ", $not_attached) . " " . (count($not_attached) === 1 ? "isn't" : "aren't")
					. " on it and will be ignored)"
				: "";

			return [
				"ok" => true,
				"summary" => "Remove " . count($attached) . " tag(s) from “{$label}”" . $note
					. ". The tags themselves are not deleted."
					. $this->aiTagModeNote($can_publish, $target["kind"]),
				"preview" => [
					"action" => "remove_tags",
					"target" => $target["kind"],
					"target_title" => $label,
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : null,
					"module_id" => $target["module_id"],
					"entry_id" => $target["entry_id"],
					"tags" => $attached,
					"ignored" => $not_attached,
					"mode" => $can_publish ? "published" : "pending",
				],
				"payload" => [
					"table" => $target["table"],
					"entry_id" => $target["entry_id"],
					"module_id" => $target["module_id"],
					"form" => (string)($target["form_id"] ?? ""),
					"page_id" => $target["kind"] === "page" ? $target["entry_id"] : 0,
					"tags" => $attached,
				],
				"fingerprint" => $this->aiTagFingerprint($target),
				"lock" => $this->aiTagLock($target),
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

			$names = $this->aiNormalizeTagNames($payload["tags"] ?? []);

			// A non-publisher's tag change is queued, exactly as their edit to any
			// other field of the same page or entry would be.
			if (empty($target["can_publish"])) {
				$ids = $this->aiCurrentTagIds($target);
				$removed = [];

				foreach ($names as $name) {
					$tag_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $name);
					$position = $tag_id ? array_search($tag_id, $ids, true) : false;

					if ($position === false) {

						continue;
					}

					unset($ids[$position]);
					$removed[] = $name;
				}

				return $this->aiQueueTagChange($target, array_values($ids), $user, "removed", $removed);
			}

			$removed = [];

			foreach ($names as $name) {
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
		 * The "…once you approve" / "…for a publisher to review" tail every other
		 * content proposal's summary carries, for a tag write.
		 */
		private function aiTagModeNote(bool $can_publish, string $kind): string {
			if ($can_publish) {

				return " The change goes live once you approve.";
			}

			return " It will be queued as a pending change on this {$kind} for a publisher to review — the live "
				. ($kind === "page" ? "page" : "entry") . "'s tags are untouched until then.";
		}

		/**
		 * The target's effective tag ids right now: what an outstanding pending change
		 * stages if it stages any, otherwise what is live. A queued tag write merges
		 * into this set rather than replacing it, because the pending change carries
		 * the *complete* resulting set (that is what the publish path replays).
		 *
		 * @param array<string,mixed> $target An aiResolveTagTarget result.
		 * @return list<int>
		 */
		private function aiCurrentTagIds(array $target): array {
			if ($target["table"] === "bigtree_pages") {

				return (new PageService())->aiPageTagIds((int)$target["entry_id"]);
			}

			return (new AutoModuleService())->aiEntryTagIds((string)$target["table"], (int)$target["entry_id"]);
		}

		/**
		 * Stage a resulting tag set as a pending change on the page or entry it
		 * belongs to, and shape the same result the live path returns.
		 *
		 * @param array<string,mixed> $target An aiResolveTagTarget result.
		 * @param list<int> $tag_ids The complete resulting tag set.
		 * @param list<string> $names The tags this proposal actually moved.
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function aiQueueTagChange(array $target, array $tag_ids, $user, string $verb, array $names): array {
			if ($target["table"] === "bigtree_pages") {
				$change_id = (new PageService())->aiQueuePageTagChange((int)$target["entry_id"], $tag_ids, $user);
			} else {
				$change_id = (new AutoModuleService())->aiQueueEntryTagChange(
					(string)$target["module_id"],
					(string)$target["table"],
					(int)$target["entry_id"],
					$tag_ids,
					$user
				);
			}

			return [
				"mode" => "pending",
				"table" => $target["table"],
				"entry_id" => $target["entry_id"],
				"page_id" => $target["table"] === "bigtree_pages" ? $target["entry_id"] : null,
				"pending_change_id" => $change_id,
				$verb => $names,
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
					// Tags are live, public-facing content, so a non-publisher's tag
					// change has to go through the queue like every other content write.
					"can_publish" => PermissionService::userHasPageAccess($user, $page_id, "p"),
					"label" => trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}",
				];
			}

			if ($module_id === "" || $entry_id < 1) {

				return ["error" => "Provide either a page_id, or a module_id and entry_id, to identify what to tag."];
			}

			// Route through the entry tools' own resolver so a multi-form module asks
			// which form rather than silently picking the first one's table — the tag
			// relation is keyed on that table, so a wrong guess attaches the tag to a
			// row the entry isn't in, and remove_tags then can't see it.
			$resolved = (new AutoModuleService())->aiResolveModuleForm($module_id, trim((string)($args["form"] ?? "")));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = (string)$resolved["table"];

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
				"form_id" => (string)($resolved["form"]["id"] ?? ""),
				// Per-row, not best-of-any-group — same rule the entry write seams use.
				"can_publish" => PermissionService::isPublisher(
					$user,
					PermissionService::userEntryLevel($user, $module, $row)
				),
				"label" => $label !== "" ? $label : "entry #{$entry_id}",
			];
		}

		/**
		 * The concurrent-edit lock descriptor for a tag target. Tags are edited from
		 * the page and entry editors' own chips, so a tag write lands in the same
		 * form someone may have open — the editor's next save rewrites the whole set.
		 *
		 * @param array<string,mixed> $target An aiResolveTagTarget result.
		 * @return array<string,mixed>
		 */
		/**
		 * The staleness descriptor for a tag change on a page or module entry.
		 *
		 * Tags live in bigtree_tags_rel rather than on the record, so a column-based
		 * descriptor has nothing to say about them — add_tags and remove_tags staged
		 * no fingerprint at all, and a tag set rewritten by somebody else inside the
		 * proposal's 24h life was merged into blind. When the change is bound for a
		 * queued draft, that draft's blob is what moves, so it is what gets watched.
		 *
		 * @param array<string,mixed> $target An aiResolveTagTarget result.
		 * @return array<string,mixed>
		 */
		private function aiTagFingerprint(array $target): array {
			$table = (string)($target["table"] ?? "");
			$entry_id = (string)($target["entry_id"] ?? "");

			if ($table === "" || $entry_id === "" || $entry_id === "0") {

				return [];
			}

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?",
				$table,
				$entry_id
			);

			if ($change_id > 0) {

				return ["type" => "pending_change", "id" => $change_id];
			}

			return ["type" => "tags", "table" => $table, "id" => $entry_id];
		}

		private function aiTagLock(array $target): array {
			$entry_id = (string)($target["entry_id"] ?? "");

			if ($entry_id === "" || $entry_id === "0") {

				return [];
			}

			if (($target["kind"] ?? "") === "page") {

				return ["table" => "bigtree_pages", "id" => $entry_id];
			}

			$module_id = (string)($target["module_id"] ?? "");

			if ($module_id === "") {

				return [];
			}

			return ["table" => "module:{$module_id}", "id" => $entry_id];
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
				// The form the proposal was staged against, so approval resolves the
				// same table rather than re-asking (or re-guessing) which one.
				"form" => (string)($payload["form"] ?? ""),
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

			// The module's forms changed since staging, so the form this was staged
			// against no longer identifies one table. Refuse rather than pick.
			if (!empty($target["ambiguous_form"])) {

				return ["mode" => "error", "message" => "This module's forms have changed since this was proposed — ask again."];
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

		// — tag record management (merge / rename) —
		//
		// add_tags/remove_tags manage which tags a *record* carries; these manage the
		// tag vocabulary itself. Tag hygiene ("we have both 'e-mail' and 'email'") is
		// a natural language task the catalog previously only claimed to cover:
		// can_manage_tags read as full management while the catalog offered attach and
		// detach alone. Administrator-only, like tag creation, and two-phase because a
		// merge deletes tag rows and rewrites every relation pointing at them.

		/**
		 * Resolve a model-supplied tag reference (name or numeric id) to its row.
		 *
		 * @param mixed $reference
		 * @return array<string,mixed>|null
		 */
		private function aiFindTag($reference): ?array {
			$reference = trim((string)$reference);

			if ($reference === "") {

				return null;
			}

			// The *name* is tried first, always. This used to prefer the id reading for
			// anything numeric, so on a site with year tags "merge 2024 into archive"
			// resolved 2024 to tag id 2024 — an unrelated tag, then irreversibly
			// deleted with its relations retargeted. A tag named like a number is far
			// more likely than a user quoting a raw row id, and merge_tags has no undo.
			$row = SQL::fetch("SELECT * FROM bigtree_tags WHERE tag = ?", $this->normalize($reference));

			if ($row) {

				return $row;
			}

			if (ctype_digit($reference)) {

				return SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", (int)$reference) ?: null;
			}

			return null;
		}

		/**
		 * Validate merging one or more tags into another. Administrator-only.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTagMerge(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can merge tags."];
			}

			$into = $this->aiFindTag($args["into"] ?? "");

			if (!$into) {

				return ["error" => "There's no tag matching \"" . trim((string)($args["into"] ?? "")) . "\" to merge "
					. "into. Use search_tags to find the tag you mean."];
			}

			$from = [];
			$unknown = [];

			foreach ((array)($args["from"] ?? []) as $reference) {
				$row = $this->aiFindTag($reference);

				if (!$row) {
					$unknown[] = trim((string)$reference);

					continue;
				}

				// Merging a tag into itself would delete it and orphan every relation
				// the merge was supposed to preserve.
				if ((int)$row["id"] === (int)$into["id"]) {

					return ["error" => "\"{$into["tag"]}\" can't be merged into itself."];
				}

				$from[(int)$row["id"]] = $row;
			}

			if ($unknown) {

				return ["error" => "No tag matches: " . implode(", ", $unknown) . ". Use search_tags to check the "
					. "names first."];
			}

			if (!$from) {

				return ["error" => "Provide the tag(s) to merge in `from`."];
			}

			$names = [];
			$moving = 0;

			foreach ($from as $row) {
				$names[] = (string)$row["tag"];
				$moving += (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", (int)$row["id"]);
			}

			return [
				"ok" => true,
				"summary" => "Merge " . implode(", ", array_map(function (string $n): string {

					return "“{$n}”";
				}, $names)) . " into “{$into["tag"]}”. " . ($moving === 1 ? "1 tagged record moves" : "{$moving} tagged "
					. "records move") . " across, and the merged tag" . (count($names) === 1 ? " is" : "s are")
					. " deleted. This can't be undone.",
				"preview" => [
					"action" => "merge_tags",
					"into" => (string)$into["tag"],
					"from" => $names,
					"records_moved" => $moving,
					"mode" => "published",
				],
				"payload" => [
					"into" => (int)$into["id"],
					"from" => array_keys($from),
				],
				// A merge deletes tag rows and retargets every relation pointing at
				// them, and has no undo — so if any of the tags involved was renamed,
				// merged or deleted since the card was drawn, this is not that merge.
				"fingerprint" => [
					"type" => "composite",
					"parts" => array_map(function (int $tag_id): array {

						return ["type" => "row", "table" => "bigtree_tags", "id" => $tag_id,
							"columns" => ["tag", "route", "usage_count"]];
					}, array_merge([(int)$into["id"]], array_keys($from))),
				],
			];
		}

		/**
		 * Execute an approved tag merge. Re-checks administrator level and re-reads
		 * every tag, then performs the same relation rewrite + delete + usage recount
		 * the REST merge route does.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiMergeTags(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can merge tags.");
			}

			$into = (int)($payload["into"] ?? 0);
			$target = $into > 0 ? SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $into) : null;

			if (!$target) {

				return ["mode" => "error", "message" => "The tag being merged into no longer exists."];
			}

			$merged = [];

			foreach ((array)($payload["from"] ?? []) as $tag_id) {
				$tag_id = (int)$tag_id;

				if ($tag_id < 1 || $tag_id === $into) {

					continue;
				}

				$row = SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $tag_id);

				if (!$row) {

					continue;
				}

				$this->retargetTagRelations($tag_id, $into);
				$this->retargetQueuedTagChanges($tag_id, $into);
				SQL::delete("bigtree_tags", $tag_id);
				$merged[] = (string)$row["tag"];
			}

			if (!$merged) {

				return ["mode" => "error", "message" => "Those tags no longer exist — nothing was merged."];
			}

			$this->recomputeUsage($into);

			return [
				"mode" => "merged",
				"tag_id" => $into,
				"tag" => (string)$target["tag"],
				"merged" => $merged,
			];
		}

		/**
		 * Validate renaming a tag. Administrator-only. A rename that collides with an
		 * existing tag is a merge in disguise, so it is refused and pointed at
		 * merge_tags rather than silently producing two tags with the same name.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTagRename(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can rename tags."];
			}

			$tag = $this->aiFindTag($args["tag"] ?? "");

			if (!$tag) {

				return ["error" => "There's no tag matching \"" . trim((string)($args["tag"] ?? "")) . "\". Use "
					. "search_tags to find it."];
			}

			$name = $this->normalize((string)($args["name"] ?? ""));

			if ($name === "") {

				return ["error" => "A new tag name is required (letters, numbers and spaces)."];
			}

			if ($name === (string)$tag["tag"]) {

				return ["error" => "“{$name}” is already this tag's name — nothing to change."];
			}

			// routes/tags.php declares tag as max:255; nothing on the AI path checked it.
			if (mb_strlen($name) > 255) {

				return ["error" => "A tag name holds at most 255 characters (this one is " . mb_strlen($name) . ")."];
			}

			$collision = SQL::fetch("SELECT * FROM bigtree_tags WHERE tag = ? AND id != ?", $name, (int)$tag["id"]);

			if ($collision) {

				return ["error" => "A tag called “{$name}” already exists. Renaming this one onto it would leave two "
					. "tags with the same name — use merge_tags to combine them instead."];
			}

			$usage = (int)($tag["usage_count"] ?? 0);

			return [
				"ok" => true,
				"summary" => "Rename the tag “{$tag["tag"]}” to “{$name}”"
					. ($usage > 0 ? ", affecting {$usage} tagged record" . ($usage === 1 ? "" : "s") : "")
					. ". Its public URL changes with it.",
				"preview" => [
					"action" => "rename_tag",
					"changes" => ["tag" => ["from" => (string)$tag["tag"], "to" => $name]],
					"tagged_records" => $usage,
					"mode" => "published",
				],
				"payload" => [
					"tag_id" => (int)$tag["id"],
					"name" => $name,
				],
				"fingerprint" => [
					"type" => "row",
					"table" => "bigtree_tags",
					"id" => (int)$tag["id"],
					"columns" => ["tag", "route"],
				],
			];
		}

		/**
		 * Execute an approved tag rename. Re-checks administrator level, that the tag
		 * still exists, and that the name is still free.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiRenameTag(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can rename tags.");
			}

			$tag_id = (int)($payload["tag_id"] ?? 0);
			$name = $this->normalize((string)($payload["name"] ?? ""));
			$tag = $tag_id > 0 ? SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $tag_id) : null;

			if (!$tag || $name === "") {

				return ["mode" => "error", "message" => "That tag no longer exists."];
			}

			// Re-checked at approval like every other staged value.
			if (mb_strlen($name) > 255) {

				return ["mode" => "error", "message" => "A tag name holds at most 255 characters."];
			}

			if (SQL::fetch("SELECT id FROM bigtree_tags WHERE tag = ? AND id != ?", $name, $tag_id)) {

				return ["mode" => "error", "message" => "A tag called “{$name}” has been created since this was "
					. "proposed — use merge_tags instead."];
			}

			SQL::update("bigtree_tags", $tag_id, [
				"tag" => $name,
				"metaphone" => metaphone($name),
				"route" => $this->uniqueRoute(BigTreeCMS::urlify($name)),
			]);

			return [
				"mode" => "renamed",
				"tag_id" => $tag_id,
				"from" => (string)$tag["tag"],
				"tag" => $name,
			];
		}
	}
