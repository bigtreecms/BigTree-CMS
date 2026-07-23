<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Hooks;
	use BigTree\Api\Json;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Services\AI\Tools\PendingChangeToolBackend;
	use BigTreeAutoModule;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Pending change inspection and approval/rejection.
	 *
	 * "Approve" semantics differ per type:
	 *   - "EDIT" (existing row): publishPendingItem applies changes to live table
	 *   - "NEW" (new entry): publishPendingItem promotes the pending change to a real row
	 *   - page changes: hand off to PageService when migrated; for v1 we publish only
	 *     module entries and mark page changes as out-of-scope for the SPA approval flow.
	 */
	class PendingChangeService implements PendingChangeToolBackend {
		public function list(Request $request) {
			$mine_only = $request->queryBool("mine");
			$me = (int)$request->user->id;

			$where = $mine_only ? " WHERE user = ?" : "";
			$args = $mine_only ? [$me] : [];

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_pending_changes" . $where,
				"SELECT id, user, date, title, `table`, item_id, type, module, pending_page_parent
				 FROM bigtree_pending_changes" . $where . " ORDER BY date DESC, id DESC",
				$args,
				function ($r) {

					return [
						"id" => (int)$r["id"],
						"user" => (int)$r["user"],
						"date" => $r["date"],
						"title" => $r["title"],
						"table" => $r["table"],
						"item_id" => $r["item_id"] !== null ? (int)$r["item_id"] : null,
						"type" => $r["type"],
						"module" => $r["module"],
						"pending_page_parent" => (int)$r["pending_page_parent"],
					];
				},
				100
			);
		}

		public function get(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforceVisibility($request->user, $row);

			$row["changes"] = Json::decode($row["changes"]);
			$row["mtm_changes"] = Json::decode($row["mtm_changes"]);
			$row["tags_changes"] = Json::decode($row["tags_changes"]);
			$row["open_graph_changes"] = Json::decode($row["open_graph_changes"]);
			$row["id"] = (int)$row["id"];
			$row["user"] = (int)$row["user"];

			return Response::ok($row);
		}

		public function approve(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			$this->applyPendingChange($row, $request->user);

			return Response::noContent();
		}

		/**
		 * Apply a queued change live and drop the queue row. Shared by approve() (REST)
		 * and the publish_pending_change AI tool so the two paths can never diverge.
		 * The caller is responsible for the publisher check first.
		 *
		 * @param array<string,mixed> $row The raw pending-change row.
		 * @param object|array $user
		 * @return array<string,mixed> A small structured outcome.
		 */
		private function applyPendingChange(array $row, $user): array {
			$id = (int)$row["id"];

			// Pages: apply the queued change live and drop the queue row.
			if ($row["table"] === "bigtree_pages") {
				(new PageService())->publishPendingChange($row, $user);

				return ["mode" => "published", "table" => "bigtree_pages", "type" => (string)$row["type"]];
			}

			// Module entries
			$module = $row["module"] ? BigTreeJSONDB::get("modules", $row["module"]) : null;

			if (!$module) {
				throw new BadRequestException("Cannot resolve module for this change", "module_unresolved");
			}

			$changes = BigTreeAutoModule::sanitizeData($row["table"], Json::decode($row["changes"]));
			$mtm_changes = Json::decode($row["mtm_changes"]);
			$tags_changes = Json::decode($row["tags_changes"]);
			$open_graph_changes = Json::decode($row["open_graph_changes"]);

			// Branch on item_id, not type: a re-edited NEW draft keeps item_id = null
			// while submitChange flips its type to "EDIT", so type is unreliable here.
			if ($row["item_id"] !== null) {
				// Existing entry — apply the change in place. updateItem deletes the
				// pending change row itself once the live row is updated.
				$item_id = (int)$row["item_id"];

				// …but only if the entry is still there. updateItem's UPDATE matches no
				// rows against a deleted entry, deletes the change anyway, and reports
				// success — so "publish this change" destroyed the change and said it
				// had published it.
				if (!SQL::fetchSingle("SELECT id FROM `" . $row["table"] . "` WHERE id = ?", $item_id)) {

					throw new BadRequestException(
						"The entry this change belongs to has been deleted, so the change can't be published.",
						"entry_deleted"
					);
				}

				BigTreeAutoModule::updateItem($row["table"], $item_id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
			} else {
				// New entry — promote the pending change to a real row. publishPendingItem
				// deletes the pending change row itself (the id is the publishing change).
				$item_id = BigTreeAutoModule::publishPendingItem($row["table"], $id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
			}

			// Re-key the draft's tracked resource allocations from "p{change}" onto the
			// now-live row (mirrors legacy dashboard/approve-change.php).
			$this->reallocatePendingResources($row["table"], $item_id, $id);

			if (!empty($row["publish_hook"]) && is_callable($row["publish_hook"])) {
				call_user_func($row["publish_hook"], $row["table"], $item_id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
			}

			return ["mode" => "published", "table" => (string)$row["table"], "item_id" => (int)$item_id];
		}

		public function reject(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			SQL::delete("bigtree_pending_changes", $id);
			// Discard the draft's resource allocations (mirrors legacy reject-change.php).
			ResourceAllocationService::deallocateResources($row["table"], "p".$id);

			return Response::noContent();
		}

		// — helpers —

		/**
		 * Re-key a published draft's resource allocations from its "p{change}" entry
		 * onto the new live row id. updateResourceAllocation is a legacy instance
		 * method (now static on ResourceAllocationService).
		 */
		private function reallocatePendingResources(string $table, $live_id, int $change_id): void {
			ResourceAllocationService::updateResourceAllocation($table, $live_id, $change_id);
		}

		private function enforceVisibility($user, array $row) {
			if ((int)$user->level >= 1) {
				return;
			}

			if ((int)$row["user"] === (int)$user->id) {
				return;
			}

			// Editors can see changes for items they have at least view access to.
			if ($row["module"] && PermissionService::userHasModuleAccess($user, $row["module"], "v")) {
				return;
			}
			throw new AuthorizationException("Not your pending change");
		}

		private function enforcePublisher($user, array $row) {
			if (!$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required");
			}
		}

		/**
		 * Non-throwing publisher check for a pending change, so the AI validation path
		 * can turn "not a publisher" into a denial instead of an exception. Publisher
		 * rights resolve against the affected page (or parent for a NEW draft) or the
		 * change's module; global admins/devs always qualify.
		 *
		 * @param object|array $user
		 * @param array<string,mixed> $row
		 */
		private function isPublisherFor($user, array $row): bool {
			if (PermissionService::level($user) >= 1) {

				return true;
			}

			if ($row["table"] === "bigtree_pages") {
				$page_id = (int)($row["item_id"] ?? 0) ?: (int)($row["pending_page_parent"] ?? 0);

				return PermissionService::userHasPageAccess($user, $page_id, "p");
			}

			$module = $row["module"] ?? "";

			return $module && PermissionService::userHasModuleAccess($user, $module, "p");
		}

		// — AI tool seam (PendingChangeToolBackend) —
		//
		// get_pending_changes surfaces the changes relevant to the user (their own
		// submissions and ones they can publish); publish_pending_change is a two-phase
		// publisher action that reuses applyPendingChange, so the AI path and the REST
		// approve path stay identical.

		/**
		 * Pending changes relevant to the acting user: ones they submitted and ones
		 * they may publish. Each row is tagged mine / can_publish for the model.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPendingChanges(int $limit, $user): array {
			$me = (int)(is_object($user) ? ($user->id ?? 0) : ($user["id"] ?? 0));
			$limit = max(1, $limit);
			$columns = "id, user, date, title, `table`, item_id, type, module, pending_page_parent";

			// An administrator or developer can publish every queued change
			// (isPublisherFor short-circuits on level), so the newest $limit rows are
			// exactly the answer — no scan and no cap at all.
			if (PermissionService::level($user) >= 1) {
				$rows = SQL::fetchAll(
					"SELECT {$columns} FROM bigtree_pending_changes ORDER BY date DESC, id DESC LIMIT " . $limit
				);
				$out = [];

				foreach ($rows as $row) {
					$out[] = $this->aiPresentPendingRow($row, (int)$row["user"] === $me, true);
				}

				return ["pending_changes" => $out];
			}

			// For an editor, publishability is a per-row permission computation with
			// no SQL equivalent. It used to be answered by scanning the newest 500
			// rows, which on a busy queue hid older changes outright. Walk the queue in
			// pages instead and stop as soon as $limit qualifying rows are collected:
			// bounded memory, and nothing is invisible just for being old.
			$out = [];
			$offset = 0;

			do {
				$rows = SQL::fetchAll(
					"SELECT {$columns} FROM bigtree_pending_changes
					 ORDER BY date DESC, id DESC LIMIT " . self::AI_PENDING_SCAN_BATCH . " OFFSET " . $offset
				);

				foreach ($rows as $row) {
					$mine = (int)$row["user"] === $me;
					$can_publish = $this->isPublisherFor($user, $row);

					if (!$mine && !$can_publish) {

						continue;
					}

					$out[] = $this->aiPresentPendingRow($row, $mine, $can_publish);

					if (count($out) >= $limit) {

						return ["pending_changes" => $out];
					}
				}

				$offset += self::AI_PENDING_SCAN_BATCH;
			} while (count($rows) === self::AI_PENDING_SCAN_BATCH);

			return ["pending_changes" => $out];
		}

		/** Rows read per pass when walking the queue for an editor. */
		private const AI_PENDING_SCAN_BATCH = 500;

		/**
		 * @param array<string,mixed> $row
		 * @return array<string,mixed>
		 */
		private function aiPresentPendingRow(array $row, bool $mine, bool $can_publish): array {

			return [
				"id" => (int)$row["id"],
				"title" => (string)$row["title"],
				"table" => (string)$row["table"],
				"item_id" => $row["item_id"] !== null ? (int)$row["item_id"] : null,
				"type" => (string)$row["type"],
				"module" => (string)$row["module"],
				"mine" => $mine,
				"can_publish" => $can_publish,
				"date" => $row["date"],
			];
		}

		/**
		 * One pending change with the actual field-level diff it would apply.
		 *
		 * The list read and the publish preview both showed only title/table/type, so
		 * an approver confirmed a publish without ever seeing what it would change.
		 * Visible to the change's own author as well as to its publishers.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetPendingChange(int $id, $user): array {
			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));
			$can_publish = $this->isPublisherFor($user, $row);

			if (!$mine && !$can_publish) {

				return ["denied" => "You can only view your own pending changes, or ones you can publish."];
			}

			return ["pending_change" => [
				"id" => $id,
				"title" => (string)$row["title"],
				"table" => (string)$row["table"],
				"item_id" => $row["item_id"] !== null ? (int)$row["item_id"] : null,
				"type" => (string)$row["type"],
				"module" => (string)$row["module"],
				"mine" => $mine,
				"can_publish" => $can_publish,
				"date" => $row["date"],
				"is_new_item" => $row["item_id"] === null,
				"changes" => $this->aiChangeDiff($row),
			]];
		}

		/**
		 * A compact field-level diff for a pending change: each changed field with the
		 * value it would replace (when the change targets an existing row).
		 *
		 * Values are length-capped so a large HTML body can't flood a proposal card
		 * or a model's context.
		 *
		 * @param array<string,mixed> $row a bigtree_pending_changes row
		 * @return list<array<string,mixed>>
		 */
		private function aiChangeDiff(array $row): array {
			$changes = Json::decode($row["changes"]);
			$table = (string)$row["table"];
			$is_page = $table === "bigtree_pages";

			// A NEW change has no live row to compare against.
			$existing = [];

			if ($row["item_id"] !== null && $table !== "") {
				$live = SQL::fetch("SELECT * FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", (int)$row["item_id"]);
				$existing = is_array($live) ? $live : [];
			}

			$out = [];

			foreach ($changes as $column => $value) {
				// Page tags ride inside `changes` as a raw array of numeric ids, which
				// tells an approver nothing about what is being published.
				if ($is_page && $column === "tags") {
					$out[] = [
						"column" => "tags",
						"to" => implode(", ", $this->aiTagNamesFor($value)),
						"from" => implode(", ", $this->aiLiveTagNames($table, (int)$row["item_id"])),
					];

					continue;
				}

				$entry = [
					"column" => (string)$column,
					"to" => $this->aiDiffValue($value),
				];

				if ($existing && array_key_exists($column, $existing)) {
					$entry["from"] = $this->aiDiffValue($existing[$column]);
				}

				$out[] = $entry;
			}

			// The other three change columns were decoded by nobody. For a *module
			// entry* tags and Open Graph live exclusively there, so a draft whose only
			// change was its tag set read as "0 fields affected" and the approver
			// published a tag rewrite sight-unseen.
			$tags_changes = Json::decode($row["tags_changes"] ?? "");

			if (!$is_page && $tags_changes) {
				$out[] = [
					"column" => "tags",
					"to" => implode(", ", $this->aiTagNamesFor($tags_changes)),
					"from" => implode(", ", $this->aiLiveTagNames($table, (int)$row["item_id"])),
				];
			}

			$open_graph = Json::decode($row["open_graph_changes"] ?? "");

			foreach (["title" => "og_title", "description" => "og_description"] as $key => $label) {
				if (array_key_exists($key, $open_graph)) {
					$out[] = ["column" => $label, "to" => $this->aiDiffValue($open_graph[$key])];
				}
			}

			$mtm = Json::decode($row["mtm_changes"] ?? "");

			foreach ($mtm as $relation) {
				if (!is_array($relation)) {

					continue;
				}

				$out[] = [
					"column" => (string)($relation["table"] ?? "related records"),
					"to" => count((array)($relation["data"] ?? [])) . " linked record(s)",
				];
			}

			return $out;
		}

		/**
		 * Resolve a list of tag ids to their names, so a diff reads as words rather
		 * than as a bare array of integers.
		 *
		 * @param mixed $ids
		 * @return list<string>
		 */
		private function aiTagNamesFor($ids): array {
			$ids = array_values(array_filter(array_map("intval", (array)$ids)));

			if (!$ids) {

				return [];
			}

			$names = SQL::fetchAllSingle(
				"SELECT tag FROM bigtree_tags WHERE id IN (" . Sanitize::placeholders($ids) . ") ORDER BY tag",
				...$ids
			);

			return array_values(array_map("strval", $names ?: []));
		}

		/**
		 * The tag names currently attached to the live record a change targets.
		 *
		 * @return list<string>
		 */
		private function aiLiveTagNames(string $table, int $entry_id): array {
			if ($table === "" || $entry_id < 1) {

				return [];
			}

			$names = SQL::fetchAllSingle(
				"SELECT t.tag FROM bigtree_tags_rel r
				 JOIN bigtree_tags t ON t.id = r.tag
				 WHERE r.`table` = ? AND r.entry = ? ORDER BY t.tag",
				$table,
				(string)$entry_id
			);

			return array_values(array_map("strval", $names ?: []));
		}

		/**
		 * @param mixed $value
		 */
		private function aiDiffValue($value): string {
			$string = is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value);

			if (mb_strlen($string) > 200) {
				$string = mb_substr($string, 0, 199) . "…";
			}

			return $string;
		}

		/**
		 * Validate publishing a pending change without applying it: existence and
		 * publisher rights. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePublishChange(array $args, $user): array {
			$id = (int)($args["change_id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			if (!$this->isPublisherFor($user, $row)) {

				return ["denied" => "Publishing this change requires publisher access, which you do not have."];
			}

			$title = trim((string)$row["title"]) ?: ("change #{$id}");

			// Without the diff the approver is confirming a publish sight-unseen —
			// the preview named the change but never said what it would do.
			$diff = $this->aiChangeDiff($row);
			$count = count($diff);

			return [
				"ok" => true,
				"summary" => "Publish the pending change “{$title}”"
					. ($count ? " ({$count} field" . ($count === 1 ? "" : "s") . " affected)" : "")
					. ". It will go live immediately once you approve.",
				"preview" => [
					"action" => "publish_pending_change",
					"change_id" => $id,
					"title" => $title,
					"table" => (string)$row["table"],
					"type" => (string)$row["type"],
					"is_new_item" => $row["item_id"] === null,
					"fields" => $diff,
				],
				"payload" => [
					"change_id" => $id,
				],
				// Pending changes collapse in place, so the blob this card diffed can be
				// wholly rewritten before the publisher clicks Approve — publishing
				// content they never saw, under an audit row saying they approved it.
				"fingerprint" => ["type" => "pending_change", "id" => $id],
			];
		}

		/**
		 * Validate rejecting (discarding) a pending change.
		 *
		 * The assistant could publish a change but never decline one, so a reviewer
		 * could only ever say yes in chat. Allowed for a publisher *or* the change's
		 * own author — withdrawing your own unpublished draft needs no approval from
		 * anyone. (The REST reject route is publisher-only; the author case is the
		 * deliberate difference, and it can only ever discard the author's own work.)
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRejectChange(array $args, $user): array {
			$id = (int)($args["change_id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));
			$can_publish = $this->isPublisherFor($user, $row);

			if (!$mine && !$can_publish) {

				return ["denied" => "You can only reject your own pending changes, or ones you have publisher access to."];
			}

			$title = trim((string)$row["title"]) ?: ("change #{$id}");
			$diff = $this->aiChangeDiff($row);
			$is_new = $row["item_id"] === null;

			// Rejecting a NEW draft throws away content that exists nowhere else;
			// rejecting an EDIT just drops the proposed changes. Say which.
			$consequence = $is_new
				? " The drafted content will be discarded — it has never been published, so it will be lost."
				: " The live version stays as it is; only the proposed changes are discarded.";

			return [
				"ok" => true,
				"summary" => "Reject the pending change “{$title}”." . $consequence . " This cannot be undone.",
				"preview" => [
					"action" => "reject_pending_change",
					"change_id" => $id,
					"title" => $title,
					"table" => (string)$row["table"],
					"type" => (string)$row["type"],
					"is_new_item" => $is_new,
					"mine" => $mine,
					"destructive" => true,
					"fields" => $diff,
				],
				"payload" => ["change_id" => $id],
				// The mirror of publish's problem: a stale reject discards work newer
				// than the card described.
				"fingerprint" => ["type" => "pending_change", "id" => $id],
			];
		}

		/**
		 * Apply an approved rejection: drop the queued change and its draft resource
		 * allocations, exactly as the REST reject route does. Re-checks rights.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws AuthorizationException
		 */
		public function aiRejectChange(array $payload, $user): array {
			$id = (int)($payload["change_id"] ?? 0);
			$row = $id > 0 ? SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id) : null;

			if (!$row) {

				return ["mode" => "error", "message" => "That pending change no longer exists (it may already have been resolved)."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));

			if (!$mine && !$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required to reject someone else's change");
			}

			SQL::delete("bigtree_pending_changes", $id);
			// Discard the draft's resource allocations (mirrors legacy reject-change.php).
			ResourceAllocationService::deallocateResources($row["table"], "p".$id);

			Hooks::fire("pending_change.rejected", [
				"id" => $id, "table" => (string)$row["table"], "via" => "ai_assistant",
			]);

			return [
				"mode" => "rejected",
				"change_id" => $id,
				"title" => trim((string)$row["title"]) ?: ("change #{$id}"),
			];
		}

		/**
		 * Apply an approved publish from a stored payload. Re-checks publisher rights,
		 * then reuses the same applyPendingChange the REST approve path uses.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws AuthorizationException
		 */
		public function aiPublishChange(array $payload, $user): array {
			$id = (int)($payload["change_id"] ?? 0);
			$row = $id > 0 ? SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id) : null;

			if (!$row) {

				return ["mode" => "error", "message" => "That pending change no longer exists (it may have already been published)."];
			}

			if (!$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required");
			}

			return $this->applyPendingChange($row, $user);
		}
	}
