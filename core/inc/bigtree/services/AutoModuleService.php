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
			$module_id = $request->route_params["id"];
			$module = $this->loadModule($module_id);
			$view_id = $request->query["view"] ?? "";

			if ($view_id) {
				$view = BigTreeAutoModule::getView($view_id);
			} elseif (!empty($module["table"])) {
				$view = BigTreeAutoModule::getViewForTable($module["table"]);
			} else {
				$views = is_array($module["views"] ?? null) ? $module["views"] : [];
				$first_id = $views ? ($views[0]["id"] ?? null) : null;
				$view = $first_id ? BigTreeAutoModule::getView($first_id) : null;
			}

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

			$payload = [
				"view" => ["id" => $view["id"] ?? null, "title" => $view["title"] ?? ""],
				"items" => array_values($results["results"]),
				"meta" => [
					"page" => $page,
					"per_page" => (int)($results["per_page"] ?? 0),
					"pages" => (int)($results["pages"] ?? 0),
				],
			];

			// Grouped views need the cached numeric `group_field` resolved to a
			// human-readable title (legacy reads from `other_table.title_field`).
			// Keys are stringified so the JSON object preserves insertion order
			// for the SPA, which iterates in the server-provided sort.
			if (($view["type"] ?? "") === "grouped" || ($view["type"] ?? "") === "images-grouped") {
				$groups = BigTreeAutoModule::getGroupsForView($view);
				$payload["groups"] = [];

				foreach ($groups as $key => $title) {
					$payload["groups"][(string)$key] = $title;
				}
			}

			return Response::ok($payload);
		}

		public function get(Request $request) {
			$module_id = $request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			$pending = BigTreeAutoModule::getPendingItem($table, $entry_id);

			if (!$pending) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $pending["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			return Response::ok($pending);
		}

		public function create(Request $request) {
			$module_id = $request->route_params["id"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			$data = $request->body;
			$mtm = (array)($data["__mtm__"] ?? []);
			$tags = (array)($data["__tags__"] ?? []);
			$og = (array)($data["__open_graph__"] ?? []);
			unset($data["__mtm__"], $data["__tags__"], $data["__open_graph__"]);

			$this->applyGeocoding($module, $table, $data);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);

			if ($user_level === "p" || ((int)$request->user->level) > 0) {
				$id = BigTreeAutoModule::createItem($table, $data, $mtm, $tags, null, $og);
				$item = BigTreeAutoModule::getItem($table, $id);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "item" => $item["item"] ?? $item,
				], ["user_id" => $request->user->id]);

				return Response::created($item["item"] ?? $item, null);
			}

			if ($user_level !== "e") {
				throw new AuthorizationException("Editor or publisher access required", "permission_denied", 403);
			}

			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $table, $data, $mtm, $tags, null, false, $og
			);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $table, "pending_id" => (int)$pending_id,
			], ["user_id" => $request->user->id]);

			return Response::created(["pending_id" => $pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			$module_id = $request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			$existing = BigTreeAutoModule::getPendingItem($table, $entry_id);

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

			$this->applyGeocoding($module, $table, $data);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);

			if ($user_level === "p" || ((int)$request->user->level) > 0) {
				BigTreeAutoModule::updateItem($table, $entry_id, $data, $mtm, $tags, $og);
				$fresh = BigTreeAutoModule::getItem($table, $entry_id);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "item" => $fresh["item"] ?? $fresh,
				], ["user_id" => $request->user->id]);

				return Response::ok($fresh);
			}

			BigTreeAutoModule::submitChange($module_id, $table, $entry_id, $data, $mtm, $tags, null, $og);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $entry_id,
			], ["user_id" => $request->user->id]);

			return Response::ok(["pending" => true]);
		}

		public function delete(Request $request) {
			$module_id = $request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			$existing = BigTreeAutoModule::getPendingItem($table, $entry_id);

			if (!$existing) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			BigTreeAutoModule::deleteItem($table, $entry_id);
			Hooks::fire("module_entry.deleted", [
				"module" => $module_id, "table" => $table, "id" => $entry_id,
			], ["user_id" => $request->user->id]);

			return Response::noContent();
		}

		public function toggleArchive(Request $request) {
			return $this->toggleFlag($request, "archived");
		}

		public function toggleApprove(Request $request) {
			return $this->toggleFlag($request, "approved");
		}

		public function toggleFeature(Request $request) {
			return $this->toggleFlag($request, "featured");
		}

		public function reorder(Request $request) {
			$module_id = $request->route_params["id"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);
			$ids = array_map("intval", (array)$request->body["ids"]);
			$pos = count($ids);

			foreach ($ids as $id) {
				SQL::update($table, $id, ["position" => $pos--]);
				BigTreeAutoModule::recacheItem($id, $table);
			}

			foreach (BigTreeAutoModule::getDependantViews($table) as $dep) {
				BigTreeAutoModule::clearCache($dep["table"]);
			}

			return Response::noContent();
		}

		// Toggle one of the three legacy boolean columns (archived / approved /
		// featured) on the module's source table. Mirrors the legacy admin
		// ajax/auto-modules/views/{archive,approve,feature}.php — publisher only,
		// gated by gbp row-level access, recaches the view-cache row on flip.
		private function toggleFlag(Request $request, string $column) {
			$module_id = $request->route_params["id"];
			$entry_id = (int)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);
			$existing = BigTreeAutoModule::getPendingItem($table, $entry_id);

			if (!$existing) {
				throw new NotFoundException("Entry $entry_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) !== "p") {
				throw new AuthorizationException("Publisher access required", "permission_denied", 403);
			}

			$current = (string)($existing["item"][$column] ?? "");
			$next = $current ? "" : "on";

			SQL::update($table, $entry_id, [$column => $next]);
			BigTreeAutoModule::recacheItem($entry_id, $table);

			Hooks::fire("module_entry.{$column}", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "value" => $next,
			], ["user_id" => $request->user->id]);

			return Response::ok([
				"id" => $entry_id,
				"column" => $column,
				"value" => $next,
			]);
		}

		// — helpers —

		private function loadModule($id) {
			$m = BigTreeJSONDB::get("modules", $id);

			if (!$m) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}

			return $m;
		}

		/**
		 * Run server-side field processors over the submitted entry data before it
		 * is persisted. Currently this covers the "geocoding" field, which has no
		 * value of its own: it concatenates its configured source columns into an
		 * address, geocodes it, and writes `latitude` / `longitude` onto the row —
		 * mirroring the legacy geocoding process.php.
		 *
		 * Unlike the legacy processor we do NOT clear existing coordinates when a
		 * geocode fails (e.g. the address fields weren't included in a partial
		 * update, or the service is unavailable); coordinates are only written on a
		 * successful lookup.
		 */
		private function applyGeocoding(array $module, string $table, array &$data): void {
			$form = null;

			foreach ((array)($module["forms"] ?? []) as $candidate) {
				if (($candidate["table"] ?? "") === $table) {
					$form = $candidate;

					break;
				}
			}

			if (!$form) {
				return;
			}

			foreach ((array)($form["fields"] ?? []) as $field) {
				if (($field["type"] ?? "") !== "geocoding") {
					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$raw = $settings["fields"] ?? [];
				$source_fields = is_array($raw) ? $raw : explode(",", (string)$raw);
				$location = [];

				foreach ($source_fields as $source_field) {
					$source_field = trim((string)$source_field);

					if ($source_field === "" || !isset($data[$source_field])) {
						continue;
					}

					$value = $data[$source_field];

					if (is_array($value)) {
						$location = array_merge($location, $value);
					} elseif ($value !== "") {
						$location[] = $value;
					}
				}

				if (!$location) {
					continue;
				}

				$geocoder = new \BigTreeGeocoding();
				$result = $geocoder->geocode(implode(", ", $location));

				if ($result) {
					$data["latitude"] = $result["latitude"];
					$data["longitude"] = $result["longitude"];
				}
			}
		}

		// Resolve the source table for an entry-level operation. Views own the
		// `table` correlation in the current data model — the module-level
		// `table` field is often empty. Callers should pass `view` in either
		// the query string or body so we can resolve. Fallbacks: a view whose
		// table matches `module["table"]`, or the module's first listed view.
		private function resolveTable(array $module, Request $request): string {
			$view_id = (string)($request->query["view"] ?? $request->body["view"] ?? "");

			if ($view_id) {
				$view = BigTreeAutoModule::getView($view_id);
			} elseif (!empty($module["table"])) {
				$view = BigTreeAutoModule::getViewForTable($module["table"]);
			} else {
				$views = is_array($module["views"] ?? null) ? $module["views"] : [];
				$first_id = $views ? ($views[0]["id"] ?? null) : null;
				$view = $first_id ? BigTreeAutoModule::getView($first_id) : null;
			}

			if ($view && !empty($view["table"])) {
				return (string)$view["table"];
			}

			if (!empty($module["table"])) {
				return (string)$module["table"];
			}

			throw new NotFoundException(
				"No view/table resolvable for module {$module["id"]}",
				"no_view",
				404
			);
		}
	}
