<?php
	namespace BigTree\Services;

	use BigTree\Api\Hooks;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTreeAutoModule;
	use BigTreeJSONDB;
	use BigTreeAdmin;
	use SQL;

	/**
	 * Module entry CRUD — thin wrapper over BigTreeAutoModule (kept; 2,295 lines
	 * and the design is sound).
	 *
	 * Per-row gbp permission is enforced in this service, since it depends on the
	 * loaded row. Module-level access is enforced upstream by Permission middleware.
	 */
	class AutoModuleService {
		public function list(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$module = $this->loadModule($module_id);
			$view_id = (int)($request->query["view"] ?? 0);
			$view = $view_id ? BigTreeAutoModule::getView($view_id) : BigTreeAutoModule::getViewForTable($module["table"]);

			if (!$view) {
				throw new NotFoundException("No view defined for module $module_id", "no_view", 404);
			}

			$page = (int)($request->query["page"] ?? 1);
			$query = (string)($request->query["q"] ?? "");
			$sort = (string)($request->query["sort"] ?? "id DESC");

			$results = BigTreeAutoModule::getSearchResults($view, $page, $query, $sort, false);

			$results["results"] = array_filter($results["results"] ?? [], function ($row) use ($request, $module) {

				return PermissionService::userRowLevel($request->user, $module, $row) !== "n";
			});

			return Response::ok([
				"view" => ["id" => $view["id"] ?? null, "title" => $view["title"] ?? ""],
				"items" => array_values($results["results"]),
				"meta" => [
					"page" => $page,
					"per_page" => (int)($results["per_page"] ?? 0),
					"pages" => (int)($results["pages"] ?? 0),
				],
			]);
		}

		public function get(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);

			$pending = BigTreeAutoModule::getPendingItem($module["table"], $entry_id);

			if (!$pending) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $pending["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			return Response::ok($pending);
		}

		public function create(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$module = $this->loadModule($module_id);

			$data = $request->body;
			$mtm = (array)($data["__mtm__"] ?? []);
			$tags = (array)($data["__tags__"] ?? []);
			$og = (array)($data["__open_graph__"] ?? []);
			unset($data["__mtm__"], $data["__tags__"], $data["__open_graph__"]);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);

			if ($user_level === "p" || ((int)$request->user->level) > 0) {
				$id = BigTreeAutoModule::createItem($module["table"], $data, $mtm, $tags, null, $og);
				$item = BigTreeAutoModule::getItem($module["table"], $id);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $module["table"], "id" => (int)$id, "item" => $item["item"] ?? $item,
				], ["user_id" => $request->user->id]);

				return Response::created($item["item"] ?? $item, null);
			}

			if ($user_level !== "e") {
				throw new AuthorizationException("Editor or publisher access required", "permission_denied", 403);
			}

			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $module["table"], $data, $mtm, $tags, null, false, $og
			);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $module["table"], "pending_id" => (int)$pending_id,
			], ["user_id" => $request->user->id]);

			return Response::created(["pending_id" => $pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);

			$existing = BigTreeAutoModule::getPendingItem($module["table"], $entry_id);

			if (!$existing) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			$data = $request->body;
			$mtm = (array)($data["__mtm__"] ?? []);
			$tags = (array)($data["__tags__"] ?? []);
			$og = (array)($data["__open_graph__"] ?? []);
			unset($data["__mtm__"], $data["__tags__"], $data["__open_graph__"]);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);

			if ($user_level === "p" || ((int)$request->user->level) > 0) {
				BigTreeAutoModule::updateItem($module["table"], $entry_id, $data, $mtm, $tags, $og);
				$fresh = BigTreeAutoModule::getItem($module["table"], $entry_id);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $module["table"], "id" => $entry_id, "item" => $fresh["item"] ?? $fresh,
				], ["user_id" => $request->user->id]);

				return Response::ok($fresh);
			}

			BigTreeAutoModule::submitChange($module_id, $module["table"], $entry_id, $data, $mtm, $tags, null, $og);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $module["table"], "id" => $entry_id,
			], ["user_id" => $request->user->id]);

			return Response::ok(["pending" => true]);
		}

		public function delete(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);

			$existing = BigTreeAutoModule::getPendingItem($module["table"], $entry_id);

			if (!$existing) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			BigTreeAutoModule::deleteItem($module["table"], $entry_id);
			Hooks::fire("module_entry.deleted", [
				"module" => $module_id, "table" => $module["table"], "id" => $entry_id,
			], ["user_id" => $request->user->id]);

			return Response::noContent();
		}

		public function reorder(Request $request) {
			$module_id = (int)$request->route_params["id"];
			$module = $this->loadModule($module_id);
			$ids = array_map("intval", (array)$request->body["ids"]);
			$pos = count($ids);

			foreach ($ids as $id) {
				SQL::update($module["table"], $id, ["position" => $pos--]);
			}

			return Response::noContent();
		}

		// — helpers —

		private function loadModule($id) {
			$m = BigTreeJSONDB::get("modules", $id);

			if (!$m) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}

			if (empty($m["table"])) {
				throw new BadRequestException("Module has no table configured", "no_table", 400);
			}
			return $m;
		}
	}
