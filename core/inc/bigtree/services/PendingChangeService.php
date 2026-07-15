<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Json;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
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
			$rows = SQL::fetchAll(
				"SELECT id, user, date, title, `table`, item_id, type, module, pending_page_parent
				 FROM bigtree_pending_changes ORDER BY date DESC, id DESC LIMIT 500"
			);

			$out = [];

			foreach ($rows as $row) {
				$mine = (int)$row["user"] === $me;
				$can_publish = $this->isPublisherFor($user, $row);

				if (!$mine && !$can_publish) {

					continue;
				}

				$out[] = [
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

				if (count($out) >= max(1, $limit)) {

					break;
				}
			}

			return ["pending_changes" => $out];
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

			return [
				"ok" => true,
				"summary" => "Publish the pending change “{$title}”. It will go live immediately once you approve.",
				"preview" => [
					"action" => "publish_pending_change",
					"change_id" => $id,
					"title" => $title,
					"table" => (string)$row["table"],
					"type" => (string)$row["type"],
				],
				"payload" => [
					"change_id" => $id,
				],
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
