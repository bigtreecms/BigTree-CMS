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
	use BigTreeCMS;
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

				// The Permission middleware only authorized access to the module in
				// the URL ({id}). A client-supplied `view` could reference a view that
				// belongs to a DIFFERENT module, which would let an authorized user
				// read or mutate a table they have no rights to. getView() stamps the
				// owning module id onto the result — require it to match this module.
				if ($view && (string)($view["module"] ?? "") !== (string)($module["id"] ?? "")) {
					throw new AuthorizationException(
						"View does not belong to this module",
						"permission_denied",
						403
					);
				}
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

			$page = max(1, (int)($request->query["page"] ?? 1));
			$query = (string)($request->query["q"] ?? "");
			$sort = (string)($request->query["sort"] ?? "id DESC");

			// The row-level permission predicate (userRowLevel) is a PHP function
			// that depends on the user's gbp map, so the database can't pre-filter
			// it. getSearchResults must therefore run BEFORE we know which rows the
			// user may see. If we let it paginate first and filtered the slice
			// afterward, a gbp-restricted user would get short/empty pages and a
			// meta count that describes rows they can't reach. Instead fetch the
			// full result set ("all"), filter it, and paginate the accessible rows
			// here so meta and the page slice both reflect the post-filter set.
			$results = BigTreeAutoModule::getSearchResults($view, "all", $query, $sort, false);

			$accessible = array_values(array_filter($results["results"] ?? [], function ($row) use ($request, $module) {

				return PermissionService::userRowLevel($request->user, $module, $row) !== "n";
			}));

			// per_page mirrors getSearchResults' own derivation (legacy reads the
			// view's setting, falling back to the admin default) — the legacy
			// function never returned it, so the previous read always yielded 0.
			$per_page = !empty($view["settings"]["per_page"])
				? (int)$view["settings"]["per_page"]
				: (int)BigTreeAdmin::$PerPage;
			$per_page = max(1, $per_page);

			$total = count($accessible);
			$pages = (int)ceil($total / $per_page);
			$pages = $pages > 0 ? $pages : 1;
			$items = array_slice($accessible, ($page - 1) * $per_page, $per_page);

			$payload = [
				"view" => ["id" => $view["id"] ?? null, "title" => $view["title"] ?? ""],
				"items" => array_values($items),
				"meta" => [
					"page" => $page,
					"per_page" => $per_page,
					"pages" => $pages,
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
			$raw_id = (string)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			[, $lookup_id] = $this->parseEntryId($raw_id);
			$pending = BigTreeAutoModule::getPendingItem($table, $lookup_id);

			if (!$pending) {
				throw new NotFoundException("Entry $raw_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $pending["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			// Resolve the draft's owner so the SPA can attribute the pending change
			// ("Your draft" vs "Draft by …") instead of assuming the current user.
			$owner_id = isset($pending["owner"]) && $pending["owner"] ? (int)$pending["owner"] : null;
			$pending["owner"] = $owner_id;
			$pending["owner_name"] = $owner_id
				? SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $owner_id)
				: null;

			return Response::ok($pending);
		}

		public function create(Request $request) {
			$module_id = $request->route_params["id"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			$data = $request->body;
			$mtm = $this->validateMtm($module, $table, (array)($data["__mtm__"] ?? []));
			$tags = (array)($data["__tags__"] ?? []);
			$og = (array)($data["__open_graph__"] ?? []);
			$publish = !empty($data["__publish__"]);
			unset($data["__mtm__"], $data["__tags__"], $data["__open_graph__"], $data["__publish__"]);

			// The primary key is authoritative from the route / auto-increment — never
			// from the request body. Allowing it through would let a caller force a
			// chosen id on create or re-key an existing row on update.
			unset($data["id"]);

			$this->applyGeocoding($module, $table, $data);
			$this->applyRoute($module, $table, $data, 0);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);
			$can_publish = $user_level === "p" || ((int)$request->user->level) > 0;

			// Publishers/admins write live only when they explicitly publish; without
			// the flag they (like editors) save a pending draft.
			if ($can_publish && $publish) {
				$id = BigTreeAutoModule::createItem($table, $data, $mtm, $tags, null, $og);
				$this->trackModuleResources($table, (int)$id, $data);
				$item = BigTreeAutoModule::getItem($table, $id);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "item" => $item["item"] ?? $item,
				], ["user_id" => $request->user->id]);

				return Response::created($item["item"] ?? $item, null);
			}

			if ($user_level !== "e" && !$can_publish) {
				throw new AuthorizationException("Editor or publisher access required", "permission_denied", 403);
			}

			// createPendingItem reads $admin->ID and tracks the audit entry against
			// the legacy admin global, which doesn't exist in the API request.
			$this->bindLegacyAdmin($request->user);

			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $table, $data, $mtm, $tags, null, false, $og
			);
			$this->trackModuleResources($table, "p".$pending_id, $data);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $table, "pending_id" => (int)$pending_id,
			], ["user_id" => $request->user->id]);

			return Response::created(["pending_id" => $pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			$module_id = $request->route_params["id"];
			$raw_id = (string)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			// A pending ("p"-prefixed) entry edits its bigtree_pending_changes row;
			// a numeric id edits a live row (directly on publish, or as a draft).
			[$is_pending, $lookup_id, $pending_change_id] = $this->parseEntryId($raw_id);
			$existing = BigTreeAutoModule::getPendingItem($table, $lookup_id);

			if (!$existing) {
				throw new NotFoundException("Entry $raw_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			$data = $request->body;
			$mtm = $this->validateMtm($module, $table, (array)($data["__mtm__"] ?? []));
			$tags = (array)($data["__tags__"] ?? []);
			$og = (array)($data["__open_graph__"] ?? []);
			$publish = !empty($data["__publish__"]);
			unset($data["__mtm__"], $data["__tags__"], $data["__open_graph__"], $data["__publish__"]);

			// The primary key is authoritative from the route / auto-increment — never
			// from the request body. Allowing it through would let a caller force a
			// chosen id on create or re-key an existing row on update.
			unset($data["id"]);

			$this->applyGeocoding($module, $table, $data);
			// A pending entry has no live row yet, so there is nothing to exclude from
			// the uniqueness check; a numeric id excludes its own live row.
			$this->applyRoute($module, $table, $data, $is_pending ? 0 : (int)$lookup_id);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);
			$can_publish = $user_level === "p" || ((int)$request->user->level) > 0;

			// Publishers/admins write live only when they explicitly publish; without
			// the flag they (like editors) submit a pending change.
			if ($can_publish && $publish) {
				// A pending entry has no live row yet — publishing it promotes the
				// pending change to a real row (with the edited data). A numeric id
				// updates its live row in place.
				if ($is_pending) {
					global $admin;

					$this->bindLegacyAdmin($request->user);
					$new_id = (int)BigTreeAutoModule::publishPendingItem($table, $pending_change_id, $data, $mtm, $tags, $og);
					// Publishing promotes the pending draft to a live row; re-key its
					// already-tracked allocations from "p{change}" onto the new live id.
					$admin->updateResourceAllocation($table, $new_id, $pending_change_id);
					$fresh = BigTreeAutoModule::getItem($table, $new_id);
					Hooks::fire("module_entry.updated", [
						"module" => $module_id, "table" => $table, "id" => $new_id, "item" => $fresh["item"] ?? $fresh,
					], ["user_id" => $request->user->id]);

					return Response::ok($fresh);
				}

				$entry_id = (int)$lookup_id;
				// Publishing a live update discards any outstanding draft of this row, so
				// drop the draft's allocations before re-scanning the live row's data.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, $entry_id
				);

				if ($pending_change_id) {
					\BigTreeAdmin::deallocateResources($table, "p".$pending_change_id);
				}

				BigTreeAutoModule::updateItem($table, $entry_id, $data, $mtm, $tags, $og);
				$this->trackModuleResources($table, $entry_id, $data);
				$fresh = BigTreeAutoModule::getItem($table, $entry_id);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "item" => $fresh["item"] ?? $fresh,
				], ["user_id" => $request->user->id]);

				return Response::ok($fresh);
			}

			// submitChange requires a logged-in legacy admin ($admin->ID / ->track()),
			// which the API request has no session for — bridge the JWT user in. It
			// understands the "p" prefix, so editing a pending entry updates its
			// existing change rather than creating a second one.
			$this->bindLegacyAdmin($request->user);

			$change_allocation_id = BigTreeAutoModule::submitChange($module_id, $table, $lookup_id, $data, $mtm, $tags, null, $og);
			$this->trackModuleResources($table, "p".$change_allocation_id, $data);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $raw_id,
			], ["user_id" => $request->user->id]);

			return Response::ok(["pending" => true]);
		}

		public function delete(Request $request) {
			$module_id = $request->route_params["id"];
			$raw_id = (string)$request->route_params["eid"];
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			// Pending (never-published) entries carry a "p" prefix in the view
			// cache, e.g. "p5". Deleting one rejects the pending change rather than
			// touching a published row — mirroring the legacy admin's delete action.
			[$is_pending, $lookup_id, $pending_change_id] = $this->parseEntryId($raw_id);
			$existing = BigTreeAutoModule::getPendingItem($table, $lookup_id);

			if (!$existing) {
				throw new NotFoundException("Entry $raw_id not found", "resource_not_found", 404);
			}

			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) === "n") {
				throw new AuthorizationException("Row access denied by group permissions", "permission_denied", 403);
			}

			if ($is_pending) {
				BigTreeAutoModule::deletePendingItem($table, $pending_change_id);
				\BigTreeAdmin::deallocateResources($table, "p".$pending_change_id);
			} else {
				BigTreeAutoModule::deleteItem($table, (int)$lookup_id);
				\BigTreeAdmin::deallocateResources($table, (int)$lookup_id);

				// Drop allocations for any outstanding draft of the deleted row too.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, (int)$lookup_id
				);

				if ($pending_change_id) {
					\BigTreeAdmin::deallocateResources($table, "p".$pending_change_id);
				}
			}

			Hooks::fire("module_entry.deleted", [
				"module" => $module_id, "table" => $table, "id" => $raw_id,
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

			// Only rows that (a) are genuine members of $table and (b) the user has
			// publisher ("p") row-level access to may be repositioned. Without this a
			// user with reorder access could rewrite the ordering of rows that
			// group-based permissions hide from them, or write positions onto ids
			// that aren't members of this table at all. Mirrors PageService::reorder.
			$rows = [];

			if ($ids) {
				$placeholders = implode(",", array_fill(0, count($ids), "?"));
				$fetched = SQL::fetchAll(
					"SELECT * FROM `$table` WHERE id IN ($placeholders)",
					...$ids
				);

				foreach ($fetched as $row) {
					$rows[(int)$row["id"]] = $row;
				}
			}

			$reorderable = $this->filterReorderableIds($request->user, $module, $rows, $ids);
			$pos = count($ids);

			foreach ($ids as $id) {
				if (isset($reorderable[$id])) {
					SQL::update($table, $id, ["position" => $pos--]);
					BigTreeAutoModule::recacheItem($id, $table);
				} else {
					// Ids the user can't reorder (or that aren't members of $table)
					// still consume their slot, so the surviving rows keep their
					// intended relative spacing.
					$pos--;
				}
			}

			foreach (BigTreeAutoModule::getDependantViews($table) as $dep) {
				BigTreeAutoModule::clearCache($dep["table"]);
			}

			return Response::noContent();
		}

		/**
		 * Build the allow-set of ids that may actually be repositioned by reorder().
		 * An id is reorderable only when its row was fetched from the module's table
		 * (so it's a genuine member) and the user has publisher ("p") row-level
		 * access to it — matching toggleFlag, since reorder is a publish-like
		 * reordering of live content. $rows is the fetched rows keyed by id (each a
		 * plain SELECT * row, the exact shape userRowLevel expects). Returns a set
		 * keyed by id (the value is true) so the caller can isset()-test it.
		 *
		 * @param array<int,array<string,mixed>> $rows
		 * @param int[] $ids
		 * @return array<int,bool>
		 */
		private function filterReorderableIds($user, array $module, array $rows, array $ids): array {
			$reorderable = [];

			foreach ($ids as $id) {
				$id = (int)$id;

				if (!isset($rows[$id])) {
					continue;
				}

				if (PermissionService::userRowLevel($user, $module, $rows[$id]) === "p") {
					$reorderable[$id] = true;
				}
			}

			return $reorderable;
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
		 * Split a route entry id into its concrete form. Pending (never-published)
		 * entries arrive with a "p" prefix (e.g. "p5") — they only exist in
		 * bigtree_pending_changes; everything else is a real numeric row id.
		 *
		 * Returns [is_pending, lookup_id, pending_change_id] where `lookup_id` is the
		 * value to hand BigTreeAutoModule::getPendingItem (which understands the "p"
		 * prefix) and `pending_change_id` is the numeric bigtree_pending_changes id
		 * (only meaningful when pending). Throws 404 for anything that's neither.
		 */
		private function parseEntryId(string $raw): array {
			if (strlen($raw) > 1 && $raw[0] === "p" && ctype_digit(substr($raw, 1))) {
				return [true, $raw, (int)substr($raw, 1)];
			}

			if (ctype_digit($raw)) {
				return [false, (string)(int)$raw, (int)$raw];
			}

			throw new NotFoundException("Entry $raw not found", "resource_not_found", 404);
		}

		/**
		 * The legacy pending-change helpers (createPendingItem / submitChange) read
		 * the global `$admin` (BigTreeAdmin) for the acting user's id and audit
		 * tracking. The API request has no admin session, so construct a bare
		 * BigTreeAdmin (its constructor is inert without a session) and populate it
		 * from the authenticated JWT user. Mirrors the pattern in SystemService.
		 */
		private function bindLegacyAdmin($user): void {
			global $admin;

			if (!($admin instanceof BigTreeAdmin)) {
				$admin = new BigTreeAdmin();
			}

			if (!$admin->ID) {
				$admin->ID = $user->id;
				$admin->Level = (int)$user->level;

				$row = SQL::fetch("SELECT permissions, timezone FROM bigtree_users WHERE id = ?", (int)$user->id);

				if ($row) {
					$admin->Permissions = json_decode($row["permissions"], true) ?: [];
					$admin->Timezone = $row["timezone"];
				}
			}
		}

		/**
		 * Resource-reference column keys for a table, collected from every module form
		 * that writes to it. Reference fields (image/file/video-reference) store a bare
		 * numeric resource id, so the allocation scanner needs the column names to spot
		 * them; content fields (html/link/etc.) embed irl:// / resource:// / file URLs
		 * that are matched without keys. Mirrors the migration's per-table key build.
		 */
		private function referenceKeysForTable(string $table): array {
			$keys = [];

			foreach (\BigTreeAdmin::getModuleForms() as $form) {
				if (($form["table"] ?? "") !== $table) {
					continue;
				}

				$keys = array_merge($keys, \BigTreeAdmin::getResourceReferenceKeys($form["fields"] ?? []));
			}

			return array_values(array_unique($keys));
		}

		/**
		 * (Re)allocate the resources referenced by a just-written module entry. The API
		 * stores data without running field processors, so we scan the persisted data
		 * directly. `$entry` is the live row id or a "p"-prefixed pending change id.
		 */
		private function trackModuleResources(string $table, $entry, array $data): void {
			\BigTreeAdmin::allocateResourcesFromData($table, $entry, $data, $this->referenceKeysForTable($table));
		}

		/**
		 * Validate the client-supplied __mtm__ descriptor against the module's form
		 * definition. The legacy admin builds the table/column identifiers server-side
		 * from trusted form settings (many-to-many/process.php); the API trusts the
		 * client, so we must reject any (table, my-id, other-id) triple not declared by
		 * a many-to-many field on this form before it reaches BigTreeAutoModule, which
		 * interpolates those identifiers raw into SQL.
		 *
		 * @return array The submitted $mtm, filtered to declared triples.
		 * @throws BadRequestException when an entry names an undeclared triple.
		 */
		private function validateMtm(array $module, string $table, array $mtm): array {
			if (!$mtm) {
				return [];
			}

			// Collect the (table, my-id, other-id) triples declared on this form.
			$allowed = [];

			foreach ((array)($module["forms"] ?? []) as $candidate) {
				if (($candidate["table"] ?? "") !== $table) {
					continue;
				}

				foreach ((array)($candidate["fields"] ?? []) as $field) {
					if (($field["type"] ?? "") !== "many-to-many") {
						continue;
					}

					$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
					$key = ($settings["mtm-connecting-table"] ?? "") . "\0"
						. ($settings["mtm-my-id"] ?? "") . "\0"
						. ($settings["mtm-other-id"] ?? "");
					$allowed[$key] = true;
				}
			}

			$clean = [];

			foreach ($mtm as $entry) {
				if (!is_array($entry)) {
					continue;
				}

				$key = ($entry["table"] ?? "") . "\0"
					. ($entry["my-id"] ?? "") . "\0"
					. ($entry["other-id"] ?? "");

				if (!isset($allowed[$key])) {
					throw new BadRequestException(
						"Many-to-many relationship is not declared by this module form",
						"invalid_mtm",
						400
					);
				}

				$clean[] = $entry;
			}

			return $clean;
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

		/**
		 * Run the server-side "route" field processor over the submitted entry data
		 * before it is persisted. Mirrors the legacy route field's process.php:
		 *
		 *  - An empty value is generated from the configured `source` column(s) by
		 *    concatenating them and urlifying the result.
		 *  - A value the editor typed is urlified as-is.
		 *  - Unless the field opts out via `not_unique`, the route is made unique by
		 *    appending `-2`, `-3`, … until no other row in the table uses it (the row
		 *    being edited is excluded via $edit_id).
		 *  - `keep_original` preserves the stored route on an existing entry.
		 *
		 * The legacy admin hid this field and only processed it on the backend; the
		 * SPA now exposes it, but generation and uniqueness still happen here so the
		 * stored value matches the legacy behavior regardless of client.
		 */
		private function applyRoute(array $module, string $table, array &$data, int $edit_id = 0): void {
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
				if (($field["type"] ?? "") !== "route") {
					continue;
				}

				$column = (string)($field["column"] ?? "");

				if ($column === "" || !array_key_exists($column, $data)) {
					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				// Keep the original route on an existing entry when configured to —
				// the editor's submitted (stored) value is left untouched.
				if (!empty($settings["keep_original"]) && $edit_id && trim((string)$data[$column]) !== "") {
					continue;
				}

				// A route the editor typed wins; an empty field regenerates from the
				// configured source column(s).
				$base = trim(strip_tags((string)$data[$column]));

				if ($base === "") {
					$source = $settings["source"] ?? "";
					$source_fields = is_array($source) ? $source : [$source];
					$parts = [];

					foreach ($source_fields as $source_field) {
						$source_field = trim((string)$source_field);

						if ($source_field === "" || !isset($data[$source_field])) {
							continue;
						}

						$value = $data[$source_field];

						if (!is_array($value) && (string)$value !== "") {
							$parts[] = strip_tags((string)$value);
						}
					}

					$base = trim(implode(" ", $parts));
				}

				$route = BigTreeCMS::urlify($base);

				// Enforce uniqueness unless the field opts out, appending -2, -3, …
				// until the route is free (excluding the row being edited). Capped at
				// 1000 attempts to avoid a timeout, matching the legacy processor.
				if (empty($settings["not_unique"]) && $route !== "") {
					$original_route = $route;
					$x = 2;

					while ($x < 1000 && SQL::exists($table, [$column => $route], $edit_id ?: null)) {
						$route = $original_route."-".$x;
						$x++;
					}

					if ($x == 1000) {
						$route = "";
					}
				}

				$data[$column] = $route;
			}
		}

		// Resolve the source table for an entry-level operation. Views own the
		// `table` correlation in the current data model — the module-level
		// `table` field is often empty. Callers should pass `view` in either
		// the query string or body so we can resolve. Fallbacks: a view whose
		// table matches `module["table"]`, or the module's first listed view.
		private function resolveTable(array $module, Request $request): string {
			$form_id = (string)($request->query["form"] ?? $request->body["form"] ?? "");

			// Form-only actions (no view) send the form id as the authoritative
			// reference. Resolve the table directly from it.
			if ($form_id) {
				$form = BigTreeAutoModule::getForm($form_id);

				// Same cross-module guard as views below: the Permission middleware
				// only authorized the module in the URL, so a client-supplied form
				// must belong to it. getForm() stamps the owning module id.
				if ($form && (string)($form["module"] ?? "") !== (string)($module["id"] ?? "")) {
					throw new AuthorizationException(
						"Form does not belong to this module",
						"permission_denied",
						403
					);
				}

				if ($form && !empty($form["table"])) {
					return (string)$form["table"];
				}

				throw new NotFoundException(
					"No table resolvable for form $form_id",
					"no_form",
					404
				);
			}

			$view_id = (string)($request->query["view"] ?? $request->body["view"] ?? "");

			if ($view_id) {
				$view = BigTreeAutoModule::getView($view_id);

				// The Permission middleware only authorized access to the module in
				// the URL ({id}). A client-supplied `view` could reference a view that
				// belongs to a DIFFERENT module, which would let an authorized user
				// read or mutate a table they have no rights to. getView() stamps the
				// owning module id onto the result — require it to match this module.
				if ($view && (string)($view["module"] ?? "") !== (string)($module["id"] ?? "")) {
					throw new AuthorizationException(
						"View does not belong to this module",
						"permission_denied",
						403
					);
				}
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
