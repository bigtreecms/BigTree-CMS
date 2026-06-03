<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
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
			$p = Pagination::offset($request, 100);
			$mine_only = !empty($request->query["mine"]);
			$me = (int)$request->user->id;

			$where = $mine_only ? " WHERE user = ?" : "";
			$args = $mine_only ? [$me] : [];

			$total = (int)SQL::fetchSingle(...array_merge(["SELECT COUNT(*) FROM bigtree_pending_changes" . $where], $args));
			$rows = SQL::fetchAll(...array_merge([
				"SELECT id, user, date, title, `table`, item_id, type, module, pending_page_parent
				 FROM bigtree_pending_changes" . $where . " ORDER BY date DESC, id DESC LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $args));

			$items = array_map(function ($r) {

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
			}, $rows);

			return Response::ok($items, Pagination::offsetMeta($p["page"], $p["per_page"], $total));
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {
				throw new NotFoundException("Pending change $id not found", "resource_not_found", 404);
			}
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
			$id = (int)$request->route_params["id"];
			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {
				throw new NotFoundException("Pending change $id not found", "resource_not_found", 404);
			}
			$this->enforcePublisher($request->user, $row);

			// Pages: apply the queued change live and drop the queue row.
			if ($row["table"] === "bigtree_pages") {
				(new PageService())->publishPendingChange($row, $request->user);

				return Response::noContent();
			}

			// Module entries
			$module = $row["module"] ? BigTreeJSONDB::get("modules", $row["module"]) : null;

			if (!$module) {
				throw new BadRequestException("Cannot resolve module for this change", "module_unresolved", 400);
			}

			if ($row["type"] === "NEW") {
				BigTreeAutoModule::publishPendingItem($row["table"], $id, []);
			} elseif ($row["type"] === "EDIT") {
				BigTreeAutoModule::publishPendingItem($row["table"], $row["item_id"], json_decode($row["changes"], true) ?: []);
				SQL::delete("bigtree_pending_changes", $id);
			} else {
				throw new BadRequestException("Unsupported pending change type: " . $row["type"], "bad_type", 400);
			}

			return Response::noContent();
		}

		public function reject(Request $request) {
			$id = (int)$request->route_params["id"];
			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {
				throw new NotFoundException("Pending change $id not found", "resource_not_found", 404);
			}
			$this->enforcePublisher($request->user, $row);

			SQL::delete("bigtree_pending_changes", $id);

			return Response::noContent();
		}

		// — helpers —

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
			throw new AuthorizationException("Not your pending change", "permission_denied", 403);
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

				throw new AuthorizationException("Publisher access required", "permission_denied", 403);
			}

			$module = $row["module"];

			if ($module && PermissionService::userHasModuleAccess($user, $module, "p")) {
				return;
			}
			throw new AuthorizationException("Publisher access required", "permission_denied", 403);
		}
	}
