<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeAdmin;
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
	class PendingChangeService {
		public function list(Request $request) {
			$mine_only = !empty($request->query["mine"]);
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

			$row["changes"] = json_decode($row["changes"] ?: "[]", true);
			$row["mtm_changes"] = json_decode($row["mtm_changes"] ?: "[]", true);
			$row["tags_changes"] = json_decode($row["tags_changes"] ?: "[]", true);
			$row["open_graph_changes"] = json_decode($row["open_graph_changes"] ?: "[]", true);
			$row["id"] = (int)$row["id"];
			$row["user"] = (int)$row["user"];

			return Response::ok($row);
		}

		public function approve(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			// Pages: apply the queued change live and drop the queue row.
			if ($row["table"] === "bigtree_pages") {
				(new PageService())->publishPendingChange($row, $request->user);

				return Response::noContent();
			}

			// Module entries
			$module = $row["module"] ? BigTreeJSONDB::get("modules", $row["module"]) : null;

			if (!$module) {
				throw new BadRequestException("Cannot resolve module for this change", "module_unresolved");
			}

			$changes = BigTreeAutoModule::sanitizeData($row["table"], json_decode($row["changes"], true) ?: []);
			$mtm_changes = json_decode($row["mtm_changes"], true) ?: [];
			$tags_changes = json_decode($row["tags_changes"], true) ?: [];
			$open_graph_changes = json_decode($row["open_graph_changes"], true) ?: [];

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

			return Response::noContent();
		}

		public function reject(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			SQL::delete("bigtree_pending_changes", $id);
			// Discard the draft's resource allocations (mirrors legacy reject-change.php).
			BigTreeAdmin::deallocateResources($row["table"], "p".$id);

			return Response::noContent();
		}

		// — helpers —

		/**
		 * Re-key a published draft's resource allocations from its "p{change}" entry
		 * onto the new live row id. updateResourceAllocation is a legacy instance
		 * method, so bridge in a bare BigTreeAdmin the way the other write services do.
		 */
		private function reallocatePendingResources(string $table, $live_id, int $change_id): void {
			global $admin;

			if (!($admin instanceof BigTreeAdmin)) {
				$admin = new BigTreeAdmin();
			}

			$admin->updateResourceAllocation($table, $live_id, $change_id);
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
			if ((int)$user->level >= 1) {
				return;
			}

			// Page changes: publisher rights are resolved against the affected page
			// (or the parent, for a not-yet-created NEW draft).
			if ($row["table"] === "bigtree_pages") {
				$page_id = (int)($row["item_id"] ?? 0) ?: (int)($row["pending_page_parent"] ?? 0);

				if (PermissionService::userHasPageAccess($user, $page_id, "p")) {
					return;
				}

				throw new AuthorizationException("Publisher access required");
			}

			$module = $row["module"];

			if ($module && PermissionService::userHasModuleAccess($user, $module, "p")) {
				return;
			}
			throw new AuthorizationException("Publisher access required");
		}
	}
