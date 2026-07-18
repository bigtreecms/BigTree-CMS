<?php
	namespace BigTree\Services;

	use BigTree\Api\Hooks;
	use BigTree\Api\Json;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\ModuleEntryToolBackend;
	use BigTreeAutoModule;
	use BigTreeJSONDB;
	use BigTreeCMS;
	use SQL;

	/**
	 * Module entry CRUD — thin wrapper over BigTreeAutoModule (kept; 2,295 lines
	 * and the design is sound).
	 *
	 * Per-row gbp permission is enforced in this service, since it depends on the
	 * loaded row. Module-level access is enforced upstream by Permission middleware.
	 */
	class AutoModuleService implements ModuleEntryToolBackend {
		use ModuleSubResourceSupport;

		// Module form field types the assistant is allowed to set: plain scalar values
		// it can synthesize safely. Everything else (uploads, matrices, relationships,
		// geocoding, routes, callouts) is omitted from the AI schema and rejected if
		// supplied — the assistant never fabricates a file reference or a relation row.
		private const AI_SIMPLE_FIELD_TYPES = [
			"text", "textarea", "html", "htmleditor", "simple-editor", "code",
			"number", "currency", "phone", "email", "color",
			"date", "datetime", "time", "select", "radio", "checkbox", "list",
		];

		public function list(Request $request) {
			$module_id = $request->routeParam("id");
			$module = $this->loadModule($module_id);
			$view_id = $request->query["view"] ?? "";
			$view = $this->resolveViewForModule($module, $view_id);

			if (!$view) {
				throw new NotFoundException("No view defined for module $module_id", "no_view");
			}

			$page = max(1, $request->queryInt("page", 1));
			$query = $request->queryString("q", "", false);
			$sort = $this->safeSort($request->queryString("sort", "id DESC", false), $view);

			// per_page mirrors getSearchResults' own derivation (legacy reads the
			// view's setting, falling back to the admin default) — the legacy
			// function never returned it, so the previous read always yielded 0.
			// Shared by both the full-set and DB-pagination paths below.
			$per_page = !empty($view["settings"]["per_page"])
				? (int)$view["settings"]["per_page"]
				: (int)SettingService::perPage();
			$per_page = max(1, $per_page);

			// The row-level permission predicate (PermissionService::userRowLevel)
			// is a PHP function the database can't pre-filter. It can only change
			// WHICH rows are visible (relative to the page slice) when gbp is
			// enabled AND the user is non-admin: admins short-circuit to "p" for
			// every row, and a gbp-disabled module resolves to the same module-level
			// permission for every row (all-or-nothing — and the user already passed
			// the route's module-permission check). In those cases per-row filtering
			// is a no-op on the page slice, so the database can paginate directly.
			//
			// IMPORTANT: this branch condition is coupled to userRowLevel's logic.
			// If userRowLevel ever gains a new per-row dimension beyond gbp, revisit
			// it. Grouped views are intentionally kept on the full-set path to avoid
			// any change to how grouping interacts with pagination.
			$gbp_enabled = !empty($module["gbp"]["enabled"]);
			$user_level = PermissionService::level($request->user);
			$grouped = in_array($view["type"] ?? "", ["grouped", "images-grouped"], true);
			$needs_full_set = ($gbp_enabled && $user_level === 0) || $grouped;

			if ($needs_full_set) {
				// Fetch the full result set ("all"), filter it, and paginate the
				// accessible rows here so meta and the page slice both reflect the
				// post-filter set (plan 006 correctness). The filter is a no-op for
				// the grouped-but-non-gbp / grouped-admin cases that reach here.
				$results = BigTreeAutoModule::getSearchResults($view, "all", $query, $sort, false);
				$rows = $results["results"] ?? [];

				if ($gbp_enabled && $user_level === 0) {
					$rows = array_values(array_filter($rows, function ($row) use ($request, $module) {

						return PermissionService::userRowLevel($request->user, $module, $row) !== "n";
					}));
				}

				$total = count($rows);
				$pages = (int)ceil($total / $per_page);
				$pages = $pages > 0 ? $pages : 1;
				$items = array_slice($rows, ($page - 1) * $per_page, $per_page);
			} else {
				// Fast path: per-row filtering can't change the slice, so let the
				// database paginate a single page. getSearchResults derives per_page
				// and pages from the same view setting / admin default used above, so
				// its "pages" agrees with the per_page we report in meta.
				$results = BigTreeAutoModule::getSearchResults($view, $page, $query, $sort, false);
				$items = $results["results"] ?? [];
				$pages = max(1, (int)($results["pages"] ?? 1));
			}

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
			$module_id = $request->routeParam("id");
			$raw_id = $request->routeParam("eid");
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			[, $lookup_id] = $this->parseEntryId($raw_id);
			$pending = BigTreeAutoModule::getPendingItem($table, $lookup_id);

			if (!$pending) {
				throw new NotFoundException("Entry $raw_id not found");
			}

			PermissionService::assertCanEditRow($request->user, $module, $pending["item"] ?? []);

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
			$module_id = $request->routeParam("id");
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			[$data, $mtm, $tags, $og, $publish, $user_level, $can_publish] = $this->prepareEntryWrite($request, $module, $table, 0);

			// Publishers/admins write live only when they explicitly publish; without
			// the flag they (like editors) save a pending draft.
			if ($can_publish && $publish) {
				$id = BigTreeAutoModule::createItem($table, $data, $mtm, $tags, null, $og);
				$this->trackModuleResources($table, (int)$id, $data);
				$item = BigTreeAutoModule::getItem($table, $id);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "item" => $item["item"] ?? $item,
				]);

				return Response::created($item["item"] ?? $item, null);
			}

			if ($user_level !== "e" && !$can_publish) {
				throw new AuthorizationException("Editor or publisher access required");
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
			]);

			return Response::created(["pending_id" => $pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			[$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id] = $this->requireEditableEntry($request);

			// A pending entry has no live row yet, so there is nothing to exclude from
			// the uniqueness check; a numeric id excludes its own live row.
			[$data, $mtm, $tags, $og, $publish, $user_level, $can_publish] = $this->prepareEntryWrite($request, $module, $table, $is_pending ? 0 : (int)$lookup_id);

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
					ResourceAllocationService::updateResourceAllocation($table, $new_id, $pending_change_id);

					return $this->respondUpdated($module_id, $table, $new_id);
				}

				$entry_id = (int)$lookup_id;
				// Publishing a live update discards any outstanding draft of this row, so
				// drop the draft's allocations before re-scanning the live row's data.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, $entry_id
				);

				if ($pending_change_id) {
					\BigTree\Services\ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
				}

				BigTreeAutoModule::updateItem($table, $entry_id, $data, $mtm, $tags, $og);
				$this->trackModuleResources($table, $entry_id, $data);

				return $this->respondUpdated($module_id, $table, $entry_id);
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
			]);

			return Response::ok(["pending" => true]);
		}

		public function delete(Request $request) {
			[$module_id, $raw_id, , $table, $is_pending, $lookup_id, $pending_change_id] = $this->requireEditableEntry($request);

			if ($is_pending) {
				BigTreeAutoModule::deletePendingItem($table, $pending_change_id);
				\BigTree\Services\ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
			} else {
				BigTreeAutoModule::deleteItem($table, (int)$lookup_id);
				\BigTree\Services\ResourceAllocationService::deallocateResources($table, (int)$lookup_id);

				// Drop allocations for any outstanding draft of the deleted row too.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, (int)$lookup_id
				);

				if ($pending_change_id) {
					\BigTree\Services\ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
				}
			}

			Hooks::fire("module_entry.deleted", [
				"module" => $module_id, "table" => $table, "id" => $raw_id,
			]);

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
			$module_id = $request->routeParam("id");
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);
			$ids = $request->bodyList("ids", "int");

			// Only rows that (a) are genuine members of $table and (b) the user has
			// publisher ("p") row-level access to may be repositioned. Without this a
			// user with reorder access could rewrite the ordering of rows that
			// group-based permissions hide from them, or write positions onto ids
			// that aren't members of this table at all. Mirrors PageService::reorder.
			$rows = [];

			if ($ids) {
				$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
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

				// Not the isPublisher() formula: userRowLevel already returns "p" for
				// admins (level > 0), so this strict "p" test is the complete
				// publisher check for a row — no separate admin bypass needed.
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
			$module_id = $request->routeParam("id");
			$entry_id = $request->routeParam("eid", "int");
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);
			$existing = BigTreeAutoModule::getPendingItem($table, $entry_id);

			if (!$existing) {
				throw new NotFoundException("Entry $entry_id not found");
			}

			// userRowLevel already folds in the admin bypass (returns "p" for level
			// > 0), so the strict "p" comparison is the full publisher check here —
			// intentionally not PermissionService::isPublisher(), which is for
			// userPageLevel/userModuleLevel results.
			if (PermissionService::userRowLevel($request->user, $module, $existing["item"] ?? []) !== "p") {
				throw new AuthorizationException("Publisher access required");
			}

			$current = (string)($existing["item"][$column] ?? "");
			$next = $current ? "" : "on";

			SQL::update($table, $entry_id, [$column => $next]);
			BigTreeAutoModule::recacheItem($entry_id, $table);

			Hooks::fire("module_entry.{$column}", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "value" => $next,
			]);

			return Response::ok([
				"id" => $entry_id,
				"column" => $column,
				"value" => $next,
			]);
		}

		// — helpers —

		/**
		 * Whitelist the client-supplied sort against the columns this view can actually
		 * sort by, returning a guaranteed-safe "<field> <DIR>" string for
		 * BigTreeAutoModule::getSearchResults — which concatenates the sort field into
		 * ORDER BY unescaped (legacy/frozen). The accepted fields are exactly the view's
		 * own field keys plus the legacy specials "id" and "_status_"; the direction is
		 * normalized to ASC/DESC. The legacy "position desc, id asc" ordering is allowed
		 * verbatim. Anything else falls back to "id DESC".
		 *
		 * The allow-list mirrors which fields BigTreeAutoModule::getSearchResults maps to
		 * column$x; if that field-mapping or the view schema changes, revisit this helper
		 * so legitimate new sortable fields aren't silently downgraded to "id DESC".
		 */
		private function safeSort(string $raw, array $view): string {
			$raw = trim($raw);

			// Legacy multi-column special the SPA may send for manual ordering.
			if (strtolower($raw) === "position desc, id asc") {

				return "position desc, id asc";
			}

			// Field is either inside backticks (`col` DIR) or the first space-delimited
			// token; strip any backticks before matching.
			if (preg_match('/^`([^`]*)`(?:\s+(.*))?$/', $raw, $m)) {
				$field = $m[1];
				$rest = $m[2] ?? "";
			} else {
				$parts = explode(" ", $raw, 2);
				$field = $parts[0] ?? "";
				$rest = $parts[1] ?? "";
			}

			$allowed = array_keys(is_array($view["fields"] ?? null) ? $view["fields"] : []);
			$allowed[] = "id";
			$allowed[] = "_status_";

			if ($field === "" || !in_array($field, $allowed, true)) {

				return "id DESC";
			}

			$direction = strtoupper(trim($rest)) === "ASC" ? "ASC" : "DESC";

			return $field . " " . $direction;
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

			throw new NotFoundException("Entry $raw not found");
		}

		/**
		 * Hydrate the legacy global $admin from the API actor for BigTreeAutoModule
		 * write paths that still read global $admin.
		 */
		private function bindLegacyAdmin($user): void {
			LegacyAdmin::bridge($user);
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

			foreach (\BigTree\Services\ModuleFormService::getModuleForms() as $form) {
				if (($form["table"] ?? "") !== $table) {
					continue;
				}

				$keys = array_merge($keys, \BigTree\Services\ResourceAllocationService::getResourceReferenceKeys($form["fields"] ?? []));
			}

			return array_values(array_unique($keys));
		}

		/**
		 * (Re)allocate the resources referenced by a just-written module entry. The API
		 * stores data without running field processors, so we scan the persisted data
		 * directly. `$entry` is the live row id or a "p"-prefixed pending change id.
		 */
		private function trackModuleResources(string $table, $entry, array $data): void {
			\BigTree\Services\ResourceAllocationService::allocateResourcesFromData($table, $entry, $data, $this->referenceKeysForTable($table));
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
					throw new BadRequestException("Many-to-many relationship is not declared by this module form", "invalid_mtm");
				}

				$clean[] = $entry;
			}

			return $clean;
		}

		/**
		 * Resolve the entry an update/delete targets and assert the caller may edit
		 * it. Shared by update() and delete(): load the module + table, parse the
		 * "eid" route param (a "p"-prefixed id addresses a pending change, a numeric
		 * id a live row), fetch the pending item (404 on miss), then run the
		 * row-level edit guard. Returns
		 * [$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id].
		 */
		private function requireEditableEntry(Request $request): array {
			$module_id = $request->routeParam("id");
			$raw_id = $request->routeParam("eid");
			$module = $this->loadModule($module_id);
			$table = $this->resolveTable($module, $request);

			[$is_pending, $lookup_id, $pending_change_id] = $this->parseEntryId($raw_id);
			$existing = BigTreeAutoModule::getPendingItem($table, $lookup_id);

			if (!$existing) {
				throw new NotFoundException("Entry $raw_id not found");
			}

			PermissionService::assertCanEditRow($request->user, $module, $existing["item"] ?? []);

			return [$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id];
		}

		/**
		 * Shared publish-respond block: fetch the fresh item, fire module_entry.updated,
		 * and return a 200. Used by both update() publish branches (pending promote
		 * and live update).
		 */
		private function respondUpdated(string $module_id, string $table, int $id): Response {
			$fresh = BigTreeAutoModule::getItem($table, $id);
			Hooks::fire("module_entry.updated", [
				"module" => $module_id, "table" => $table, "id" => $id, "item" => $fresh["item"] ?? $fresh,
			]);

			return Response::ok($fresh);
		}

		/**
		 * The shared entry-write payload prep for create() and update(): pull the
		 * body, split the special `__mtm__`/`__tags__`/`__open_graph__`/`__publish__`
		 * keys out, validate the MTM set, run the geocoding/route field processors,
		 * and resolve the caller's module level + publish capability.
		 * $route_exclude_id is the live row id to exclude from the route-uniqueness
		 * check (0 on create or when editing a pending entry that has no live row).
		 * Returns [$data, $mtm, $tags, $og, $publish, $user_level, $can_publish].
		 */
		private function prepareEntryWrite(Request $request, array $module, string $table, int $route_exclude_id): array {
			$module_id = $request->routeParam("id");
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

			$this->applyEntryProcessors($module, $table, $data, $route_exclude_id);

			$user_level = PermissionService::userModuleLevel($request->user, $module_id);
			$can_publish = PermissionService::isPublisher($request->user, $user_level);

			return [$data, $mtm, $tags, $og, $publish, $user_level, $can_publish];
		}

		/**
		 * Run every server-side entry field processor over $data before it is
		 * persisted. Request-free so both the REST write path (prepareEntryWrite)
		 * and the AI execute path (aiCreateEntry/aiUpdateEntry) share one
		 * implementation — the AI path skipped these entirely before, producing
		 * entries with no route and no coordinates.
		 *
		 * $route_exclude_id is the live row id to exclude from the route-uniqueness
		 * check (0 on create).
		 */
		private function applyEntryProcessors(array $module, string $table, array &$data, int $route_exclude_id): void {
			$this->applyGeocoding($module, $table, $data);
			$this->applyRoute($module, $table, $data, $route_exclude_id);
		}

		/**
		 * The AI equivalent of the processor pass prepareEntryWrite runs, adapted to
		 * two differences in the AI path:
		 *
		 *  1. The model only ever submits the sifted *simple* fields — `route` and
		 *     `geocoding` are deliberately not AI-settable — so applyRoute would
		 *     never fire (it skips route columns absent from the data). The route
		 *     column is seeded here so generation-from-source triggers.
		 *  2. An AI update is a partial write, whereas the REST path always receives
		 *     the whole form body. Running the processors over the partial set alone
		 *     would geocode a fragment of an address, or regenerate a route from only
		 *     the columns that happened to change. So the processors run over the
		 *     live row merged with the changes, and only the *derived* columns are
		 *     copied back onto the data actually written.
		 *
		 * @param array<string,mixed> $data the sifted values being written (mutated)
		 * @param array<string,mixed> $row the existing live row, or [] on create
		 */
		private function aiApplyEntryProcessors(array $module, string $table, array &$data, array $row, int $exclude_id): void {
			$form = $this->formForTable($module, $table);

			if (!$form) {
				return;
			}

			$is_create = !$row;
			$merged = array_merge($row, $data);
			$derived = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$type = (string)($field["type"] ?? "");

				if ($type === "geocoding") {
					$derived[] = "latitude";
					$derived[] = "longitude";

					continue;
				}

				if ($type !== "route") {
					continue;
				}

				$column = (string)($field["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				// On update, leave the stored route alone unless one of its source
				// columns is actually changing — an unrelated edit must not re-route
				// (and so 404) a live entry. keep_original means never regenerate.
				if (!$is_create && (!empty($settings["keep_original"]) || !$this->routeSourceIsChanging($settings, $data))) {
					continue;
				}

				$merged[$column] = "";
				$derived[] = $column;
			}

			if (!$derived) {
				return;
			}

			$this->applyEntryProcessors($module, $table, $merged, $exclude_id);

			foreach ($derived as $column) {
				if (array_key_exists($column, $merged)) {
					$data[$column] = $merged[$column];
				}
			}
		}

		/**
		 * True when any of a route field's configured source columns appears in the
		 * data being written — the "regenerate when the inputs change" signal.
		 *
		 * @param array<string,mixed> $settings
		 * @param array<string,mixed> $data
		 */
		private function routeSourceIsChanging(array $settings, array $data): bool {
			$source = $settings["source"] ?? "";
			$source_fields = is_array($source) ? $source : [$source];

			foreach ($source_fields as $source_field) {
				$source_field = trim((string)$source_field);

				if ($source_field !== "" && array_key_exists($source_field, $data)) {

					return true;
				}
			}

			return false;
		}

		/** The module form whose `table` matches $table, or null — the find-form-by-table scan the field processors share. */
		private function formForTable(array $module, string $table): ?array {
			foreach ((array)($module["forms"] ?? []) as $candidate) {
				if (($candidate["table"] ?? "") === $table) {

					return $candidate;
				}
			}

			return null;
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
			$form = $this->formForTable($module, $table);

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
			$form = $this->formForTable($module, $table);

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
					$found = false;

					// Test each candidate — the bare route first, then -2, -3, …, -999
					// — and stop on the first one that is free. Only blank the route if
					// every candidate up to the cap genuinely collides.
					while ($x <= 1000) {
						if (!SQL::exists($table, [$column => $route], $edit_id ?: null)) {
							$found = true;

							break;
						}

						$route = $original_route."-".$x;
						$x++;
					}

					if (!$found) {
						$route = "";
					}
				}

				$data[$column] = $route;
			}
		}

		// Resolve which view applies to a module request, enforcing the
		// cross-module ownership guard once for both `list()` and `resolveTable()`.
		// Fallback chain: an explicit (client-supplied) view id, else a view whose
		// table matches `module["table"]`, else the module's first listed view.
		// Returns null when no view resolves; callers decide how to react.
		private function resolveViewForModule(array $module, string $view_id): ?array {
			if ($view_id) {
				$view = BigTreeAutoModule::getView($view_id);

				// The Permission middleware only authorized access to the module in
				// the URL ({id}). A client-supplied `view` could reference a view that
				// belongs to a DIFFERENT module, which would let an authorized user
				// read or mutate a table they have no rights to. getView() stamps the
				// owning module id onto the result — require it to match this module.
				if ($view && (string)($view["module"] ?? "") !== (string)($module["id"] ?? "")) {
					throw new AuthorizationException("View does not belong to this module");
				}

				return $view ?: null;
			}

			if (!empty($module["table"])) {
				return BigTreeAutoModule::getViewForTable($module["table"]) ?: null;
			}

			$views = is_array($module["views"] ?? null) ? $module["views"] : [];
			$first_id = $views ? ($views[0]["id"] ?? null) : null;

			return $first_id ? (BigTreeAutoModule::getView($first_id) ?: null) : null;
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
					throw new AuthorizationException("Form does not belong to this module");
				}

				if ($form && !empty($form["table"])) {
					return (string)$form["table"];
				}

				throw new NotFoundException("No table resolvable for form $form_id", "no_form");
			}

			$view_id = (string)($request->query["view"] ?? $request->body["view"] ?? "");
			$view = $this->resolveViewForModule($module, $view_id);

			if ($view && !empty($view["table"])) {
				return (string)$view["table"];
			}

			if (!empty($module["table"])) {
				return (string)$module["table"];
			}

			throw new NotFoundException("No view/table resolvable for module {$module["id"]}", "no_view");
		}

		// — AI tool seam (ModuleEntryToolBackend) —
		//
		// The assistant creates/updates module entries through BigTreeAutoModule (same
		// as create()/update()), but only the module form's simple scalar fields, and
		// always two-phase. Access is re-checked here — module edit to stage, publisher
		// to write live, per-row gbp on update — never in the model.

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryCreate(array $args, $user): array {
			$module_id = (string)($args["module_id"] ?? "");
			$resolved = $this->aiResolveModuleForm($module_id, (string)($args["form"] ?? ""));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = $resolved["table"];
			$schema = $resolved["schema"];

			if (PermissionService::userModuleLevel($user, $module["id"]) === "n"
				|| !PermissionService::userHasModuleAccess($user, $module["id"], "e")) {

				return ["denied" => "You do not have permission to add entries to this module."];
			}

			$provided = is_array($args["data"] ?? null) ? $args["data"] : [];

			// No data → hand back the settable fields so the model can fill them in and
			// retry (this doubles as schema discovery).
			if (!$provided) {

				return ["error" => "Provide a \"data\" object with the entry's field values. Settable fields: "
					. $this->aiDescribeSchema($schema)];
			}

			$sifted = $this->aiSiftEntryData($schema, $provided);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$data = $sifted["data"];
			$missing = $this->aiMissingRequired($schema, $data);

			if ($missing) {

				return ["error" => "These required fields are missing: " . implode(", ", $missing) . "."];
			}

			$rank = PermissionService::userModuleLevel($user, $module["id"]);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$name = (string)($module["name"] ?? $module["id"]);
			$blocked = $resolved["blocked_required"];

			// This form requires at least one field the assistant can't author. A
			// publisher's approval would put an incomplete record straight onto the
			// live site, so refuse; a non-publisher's write only ever lands in the
			// pending queue, where a human completes it before it goes live — allow
			// that, but say plainly what will still be missing.
			if ($blocked && $can_publish) {

				return ["error" => "This module requires fields the assistant can't fill in: "
					. implode(", ", $blocked) . ". Create this entry in the admin UI instead."];
			}

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: " It will be queued as a pending entry for a publisher to review.";

			if ($blocked) {
				$mode_note .= " Note that " . implode(", ", $blocked)
					. " " . (count($blocked) === 1 ? "is required but cannot" : "are required but cannot")
					. " be set by the assistant — a publisher must fill "
					. (count($blocked) === 1 ? "it" : "them") . " in before this entry can go live.";
			}

			$preview = [
				"action" => "create_module_entry",
				"module" => $name,
				"fields" => $this->aiPreviewEntryData($schema, $data),
				"mode" => $can_publish ? "published" : "pending",
			];

			if ($blocked) {
				$preview["incomplete_required"] = $blocked;
			}

			return [
				"ok" => true,
				"summary" => "Create a new entry in the “{$name}” module." . $mode_note,
				"preview" => $preview,
				"payload" => [
					"module_id" => (string)$module["id"],
					"table" => $table,
					"data" => $data,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateEntry(array $payload, $user): array {
			$module_id = (string)($payload["module_id"] ?? "");
			$table = (string)($payload["table"] ?? "");
			$data = is_array($payload["data"] ?? null) ? $payload["data"] : [];

			if (!PermissionService::userHasModuleAccess($user, $module_id, "e")) {
				throw new AuthorizationException("Insufficient module permission to create entry (e required)");
			}

			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!$module) {

				return ["mode" => "error", "message" => "That module no longer exists."];
			}

			// Route/geocoding are derived server-side, never supplied by the model.
			// Run them here (at approval) rather than at staging so route uniqueness
			// is evaluated against the table as it stands at write time.
			$this->aiApplyEntryProcessors($module, $table, $data, [], 0);

			$rank = PermissionService::userModuleLevel($user, $module_id);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$this->bindLegacyAdmin($user);

			if ($can_publish) {
				$id = BigTreeAutoModule::createItem($table, $data, [], [], null, []);
				$this->trackModuleResources($table, (int)$id, $data);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "via" => "ai_assistant",
				]);

				return ["mode" => "published", "module" => $module_id, "entry_id" => (int)$id];
			}

			$pending_id = BigTreeAutoModule::createPendingItem($module_id, $table, $data, [], [], null, false, []);
			$this->trackModuleResources($table, "p".$pending_id, $data);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $table, "pending_id" => (int)$pending_id, "via" => "ai_assistant",
			]);

			return ["mode" => "pending", "module" => $module_id, "pending_id" => (int)$pending_id];
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryUpdate(array $args, $user): array {
			$module_id = (string)($args["module_id"] ?? "");
			$resolved = $this->aiResolveModuleForm($module_id, (string)($args["form"] ?? ""));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = $resolved["table"];
			$schema = $resolved["schema"];

			$entry_id = (int)($args["entry_id"] ?? 0);

			if ($entry_id < 1) {

				return ["error" => "An entry_id is required to edit a module entry."];
			}

			if (!PermissionService::userHasModuleAccess($user, $module["id"], "e")) {

				return ["denied" => "You do not have permission to edit entries in this module."];
			}

			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$row) {

				return ["error" => "Entry {$entry_id} does not exist in this module."];
			}

			// Per-row group-based-permission check (assertCanEditRow's non-throwing core).
			if (PermissionService::userRowLevel($user, $module, $row) === "n") {

				return ["denied" => "You do not have permission to edit this specific entry."];
			}

			$provided = is_array($args["data"] ?? null) ? $args["data"] : [];

			if (!$provided) {

				return ["error" => "Provide a \"data\" object with the fields to change. Settable fields: "
					. $this->aiDescribeSchema($schema)];
			}

			$sifted = $this->aiSiftEntryData($schema, $provided);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$data = $sifted["data"];

			if (!$data) {

				return ["error" => "No settable fields were supplied — nothing to update."];
			}

			$rank = PermissionService::userModuleLevel($user, $module["id"]);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$name = (string)($module["name"] ?? $module["id"]);

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: " It will be queued as a pending change for a publisher to review.";

			return [
				"ok" => true,
				"summary" => "Update entry #{$entry_id} in the “{$name}” module." . $mode_note,
				"preview" => [
					"action" => "update_module_entry",
					"module" => $name,
					"entry_id" => $entry_id,
					"fields" => $this->aiPreviewEntryData($schema, $data, $row),
					"mode" => $can_publish ? "published" : "pending",
				],
				"payload" => [
					"module_id" => (string)$module["id"],
					"table" => $table,
					"entry_id" => $entry_id,
					"data" => $data,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateEntry(array $payload, $user): array {
			$module_id = (string)($payload["module_id"] ?? "");
			$table = (string)($payload["table"] ?? "");
			$entry_id = (int)($payload["entry_id"] ?? 0);
			$data = is_array($payload["data"] ?? null) ? $payload["data"] : [];

			if (!PermissionService::userHasModuleAccess($user, $module_id, "e")) {
				throw new AuthorizationException("Insufficient module permission to edit entry (e required)");
			}

			$module = BigTreeJSONDB::get("modules", $module_id);
			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$module || !$row) {

				return ["mode" => "error", "message" => "That entry no longer exists."];
			}

			PermissionService::assertCanEditRow($user, $module, $row);

			// Same derived-field pass as create, but the route is only regenerated
			// when one of its source columns is among the fields being changed —
			// otherwise an unrelated edit would silently re-route the entry.
			$this->aiApplyEntryProcessors($module, $table, $data, $row, $entry_id);

			$rank = PermissionService::userModuleLevel($user, $module_id);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$this->bindLegacyAdmin($user);

			if ($can_publish) {
				// Drop any outstanding draft's allocations before re-scanning the live row.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, $entry_id
				);

				if ($pending_change_id) {
					ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
				}

				BigTreeAutoModule::updateItem($table, $entry_id, $data, [], [], []);
				$this->trackModuleResources($table, $entry_id, $data);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
				]);

				return ["mode" => "published", "module" => $module_id, "entry_id" => $entry_id];
			}

			$change_allocation_id = BigTreeAutoModule::submitChange($module_id, $table, $entry_id, $data, [], [], null, []);
			$this->trackModuleResources($table, "p".$change_allocation_id, $data);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
			]);

			return ["mode" => "pending", "module" => $module_id, "entry_id" => $entry_id];
		}

		// The boolean columns set_module_entry_flag can flip, mapped to how they read
		// in a proposal summary. Mirrors the legacy archive/approve/feature actions.
		private const AI_ENTRY_FLAGS = [
			"archived" => ["on" => "Archive", "off" => "Restore"],
			"featured" => ["on" => "Feature", "off" => "Un-feature"],
			"approved" => ["on" => "Approve", "off" => "Un-approve"],
		];

		/**
		 * Validate flipping one of a module entry's boolean flags (archived, featured,
		 * approved). Publisher-only, per-row, mirroring toggleFlag's gate.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryFlag(array $args, $user): array {
			$flag = strtolower(trim((string)($args["flag"] ?? "")));

			if (!isset(self::AI_ENTRY_FLAGS[$flag])) {

				return ["error" => "flag must be one of: " . implode(", ", array_keys(self::AI_ENTRY_FLAGS)) . "."];
			}

			if (!array_key_exists("value", $args)) {

				return ["error" => "A value (true to set the flag, false to clear it) is required."];
			}

			$resolved = $this->aiResolveEntryForWrite($args, $user);

			if (isset($resolved["error"]) || isset($resolved["denied"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = $resolved["table"];
			$entry_id = $resolved["entry_id"];
			$row = $resolved["row"];

			// Not every module table carries all three legacy flag columns; setting one
			// that doesn't exist would fail at the UPDATE with an opaque SQL error.
			$description = SQL::describeTable($table);

			if (!$description || !isset($description["columns"][$flag])) {

				return ["error" => "Entries in this module have no \"{$flag}\" flag."];
			}

			// Publisher-only, matching the REST toggle. userRowLevel folds in the admin
			// bypass, so the strict "p" comparison is the whole check.
			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {

				return ["denied" => "Changing this requires publisher access on this entry, which you do not have."];
			}

			$value = !empty($args["value"]) && $args["value"] !== "false";
			$current = (string)($row[$flag] ?? "") !== "";

			if ($value === $current) {

				return ["error" => "That entry is already " . ($value ? "" : "not ") . $flag . "."];
			}

			$name = (string)($module["name"] ?? $module["id"]);
			$verb = $value ? self::AI_ENTRY_FLAGS[$flag]["on"] : self::AI_ENTRY_FLAGS[$flag]["off"];
			$label = $this->aiEntryLabel($row, $entry_id);

			return [
				"ok" => true,
				"summary" => "{$verb} “{$label}” in the “{$name}” module. This takes effect live once you approve.",
				"preview" => [
					"action" => "set_module_entry_flag",
					"module" => $name,
					"entry_id" => $entry_id,
					"entry" => $label,
					"flag" => $flag,
					"from" => $current,
					"to" => $value,
				],
				"payload" => [
					"module_id" => (string)$module["id"],
					"table" => $table,
					"entry_id" => $entry_id,
					"flag" => $flag,
					"value" => $value,
				],
			];
		}

		/**
		 * Execute an approved flag change. Re-checks publisher access on the row.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiSetEntryFlag(array $payload, $user): array {
			$module_id = (string)($payload["module_id"] ?? "");
			$table = (string)($payload["table"] ?? "");
			$entry_id = (int)($payload["entry_id"] ?? 0);
			$flag = (string)($payload["flag"] ?? "");

			if (!isset(self::AI_ENTRY_FLAGS[$flag])) {

				return ["mode" => "error", "message" => "That flag can no longer be changed."];
			}

			$module = BigTreeJSONDB::get("modules", $module_id);
			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$module || !$row) {

				return ["mode" => "error", "message" => "That entry no longer exists."];
			}

			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {
				throw new AuthorizationException("Publisher access required to change entry flags");
			}

			$next = !empty($payload["value"]) ? "on" : "";
			SQL::update($table, $entry_id, [$flag => $next]);
			BigTreeAutoModule::recacheItem($entry_id, $table);

			Hooks::fire("module_entry.{$flag}", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "value" => $next, "via" => "ai_assistant",
			]);

			return [
				"mode" => "updated",
				"module" => $module_id,
				"entry_id" => $entry_id,
				"flag" => $flag,
				"value" => $next !== "",
			];
		}

		/**
		 * Validate deleting a module entry. Publisher-only and irreversible, so the
		 * proposal spells out what is being destroyed rather than just naming an id.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryDelete(array $args, $user): array {
			$resolved = $this->aiResolveEntryForWrite($args, $user);

			if (isset($resolved["error"]) || isset($resolved["denied"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$row = $resolved["row"];
			$entry_id = $resolved["entry_id"];

			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {

				return ["denied" => "Deleting an entry requires publisher access on it, which you do not have."];
			}

			$name = (string)($module["name"] ?? $module["id"]);
			$label = $this->aiEntryLabel($row, $entry_id);

			return [
				"ok" => true,
				"summary" => "Permanently delete “{$label}” (entry #{$entry_id}) from the “{$name}” module. "
					. "This cannot be undone.",
				"preview" => [
					"action" => "delete_module_entry",
					"module" => $name,
					"entry_id" => $entry_id,
					"entry" => $label,
					"destructive" => true,
				],
				"payload" => [
					"module_id" => (string)$module["id"],
					"table" => $resolved["table"],
					"entry_id" => $entry_id,
				],
			];
		}

		/**
		 * Execute an approved entry delete, reusing the same deallocation the REST
		 * delete does so no orphaned resource allocations are left behind.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiDeleteEntry(array $payload, $user): array {
			$module_id = (string)($payload["module_id"] ?? "");
			$table = (string)($payload["table"] ?? "");
			$entry_id = (int)($payload["entry_id"] ?? 0);

			$module = BigTreeJSONDB::get("modules", $module_id);
			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$module || !$row) {

				return ["mode" => "error", "message" => "That entry no longer exists (it may already have been deleted)."];
			}

			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {
				throw new AuthorizationException("Publisher access required to delete an entry");
			}

			$this->bindLegacyAdmin($user);
			BigTreeAutoModule::deleteItem($table, $entry_id);
			ResourceAllocationService::deallocateResources($table, $entry_id);

			// Drop allocations for any outstanding draft of the deleted row too.
			$pending_change_id = SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, $entry_id
			);

			if ($pending_change_id) {
				ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
			}

			Hooks::fire("module_entry.deleted", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
			]);

			return ["mode" => "deleted", "module" => $module_id, "entry_id" => $entry_id];
		}

		/**
		 * Shared lookup for the entry-level lifecycle tools: resolve the module, its
		 * table, and the live row, enforcing module access along the way.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function aiResolveEntryForWrite(array $args, $user): array {
			$module_id = (string)($args["module_id"] ?? "");
			$resolved = $this->aiResolveModuleForm($module_id, (string)($args["form"] ?? ""));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = $resolved["table"];
			$entry_id = (int)($args["entry_id"] ?? 0);

			if ($entry_id < 1) {

				return ["error" => "An entry_id is required."];
			}

			if (!PermissionService::userHasModuleAccess($user, (string)$module["id"], "e")) {

				return ["denied" => "You do not have permission to change entries in this module."];
			}

			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$row) {

				return ["error" => "Entry {$entry_id} does not exist in this module."];
			}

			return ["module" => $module, "table" => $table, "entry_id" => $entry_id, "row" => $row];
		}

		/**
		 * A human label for an entry in a proposal — the first title-ish column that
		 * has a value, falling back to the id. Approving "delete entry #418" without
		 * knowing what #418 is would be approving blind.
		 *
		 * @param array<string,mixed> $row
		 */
		private function aiEntryLabel(array $row, int $entry_id): string {
			foreach (["title", "name", "headline", "nav_title", "subject"] as $column) {
				$value = trim((string)($row[$column] ?? ""));

				if ($value !== "") {

					return mb_strlen($value) > 100 ? mb_substr($value, 0, 99) . "…" : $value;
				}
			}

			return "entry #{$entry_id}";
		}

		/**
		 * Resolve a module id to its record, default form table, and the AI-settable
		 * field schema. Returns ["error" => string] when the module or a usable form
		 * can't be found.
		 *
		 * @return array<string,mixed>
		 */
		private function aiResolveModuleForm(string $module_id, string $form_id = ""): array {
			if ($module_id === "") {

				return ["error" => "A module_id is required."];
			}

			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!$module) {
				$module = BigTreeJSONDB::get("modules", $module_id, "route");
			}

			if (!$module) {

				return ["error" => "Module \"{$module_id}\" does not exist."];
			}

			$forms = array_values(is_array($module["forms"] ?? null) ? $module["forms"] : []);
			$form_id = trim($form_id);

			if ($form_id !== "") {
				$form = null;

				foreach ($forms as $candidate) {
					if ((string)($candidate["id"] ?? "") === $form_id) {
						$form = $candidate;

						break;
					}
				}

				if (!$form) {

					return ["error" => "Form \"{$form_id}\" does not belong to this module. Available forms: "
						. $this->aiDescribeForms($forms) . "."];
				}
			} else {
				// This module has more than one form, each with its own table — picking
				// the first would silently validate and write against the wrong one.
				// Surface the choice instead (the tools turn this into needs_input).
				if (count($forms) > 1) {

					return [
						"ambiguous_form" => true,
						"module" => $module,
						"forms" => array_map(function (array $f): array {

							return [
								"id" => (string)($f["id"] ?? ""),
								"title" => (string)($f["title"] ?? $f["id"] ?? ""),
								"table" => (string)($f["table"] ?? ""),
							];
						}, $forms),
					];
				}

				$form = $forms[0] ?? null;
			}

			$table = (string)($form["table"] ?? $module["table"] ?? "");

			if ($table === "") {

				return ["error" => "This module has no editable entry form."];
			}

			return [
				"module" => $module,
				"table" => $table,
				"form" => $form,
				"schema" => $this->aiEntrySchema($form),
				"blocked_required" => $this->aiRequiredUnsettableFields($form),
			];
		}

		/**
		 * A one-line "id (Title)" list of a module's forms for an error message.
		 *
		 * @param list<array<string,mixed>> $forms
		 */
		private function aiDescribeForms(array $forms): string {
			$parts = [];

			foreach ($forms as $form) {
				$id = (string)($form["id"] ?? "");
				$title = (string)($form["title"] ?? "");
				$parts[] = $title !== "" ? "{$id} ({$title})" : $id;
			}

			return $parts ? implode(", ", $parts) : "(none)";
		}

		/**
		 * The full field list for a module's entry form — every field, with its type,
		 * whether it's required, and whether the assistant can actually set it.
		 *
		 * Previously the settable-field list only ever appeared inside an error
		 * string, so the model had to burn a failed call to discover the schema, and
		 * fields it could never fill were invisible until a write was refused.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiModuleSchema(string $module_id, string $form_id, $user): array {
			$resolved = $this->aiResolveModuleForm($module_id, $form_id);

			if (isset($resolved["error"])) {

				return $resolved;
			}

			if (!empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];

			if (PermissionService::userModuleLevel($user, (string)$module["id"]) === "n") {

				return ["denied" => "You do not have access to this module."];
			}

			$fields = [];

			foreach ((array)($resolved["form"]["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$type = (string)($field["type"] ?? "text");
				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$settable = in_array($type, self::AI_SIMPLE_FIELD_TYPES, true);
				$derived = $type === "route" || $type === "geocoding";

				$entry = [
					"column" => $column,
					"title" => (string)($field["title"] ?? $column),
					"type" => $type,
					"required" => !empty($settings["required"]),
					"assistant_can_set" => $settable,
				];

				if (!$settable) {
					$entry["reason"] = $derived
						? "Generated automatically when the entry is saved."
						: "This field type can't be authored by the assistant — it must be filled in the admin UI.";
				}

				$fields[] = $entry;
			}

			return ["schema" => [
				"module_id" => (string)$module["id"],
				"module_name" => (string)($module["name"] ?? $module["id"]),
				"form_id" => (string)($resolved["form"]["id"] ?? ""),
				"table" => $resolved["table"],
				"fields" => $fields,
				"blocked_required" => $resolved["blocked_required"],
				"your_access_level" => PermissionService::userModuleLevel($user, (string)$module["id"]),
			]];
		}

		/**
		 * Required form fields whose type the assistant cannot author (uploads,
		 * media, relationships, matrices, callouts…). aiEntrySchema drops these
		 * before the required check runs, so without this scan an AI-created entry
		 * passes validation while being exactly the record the admin UI's own
		 * required check would refuse to save.
		 *
		 * Returned as "Title (column)" strings for a human-readable error.
		 *
		 * @param array<string,mixed>|null $form
		 * @return list<string>
		 */
		private function aiRequiredUnsettableFields(?array $form): array {
			$blocked = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");
				$type = (string)($field["type"] ?? "text");
				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				if ($column === "" || empty($settings["required"])) {
					continue;
				}

				if (in_array($type, self::AI_SIMPLE_FIELD_TYPES, true)) {
					continue;
				}

				// Derived server-side by applyEntryProcessors — required or not, the
				// model is not expected to supply them and they will be populated.
				if ($type === "route" || $type === "geocoding") {
					continue;
				}

				$blocked[] = (string)($field["title"] ?? $column) . " ({$column}, {$type})";
			}

			return $blocked;
		}

		/**
		 * The AI-settable field schema for a module form: simple scalar fields only,
		 * keyed by column.
		 *
		 * @param array<string,mixed>|null $form
		 * @return array<string,array<string,mixed>>
		 */
		private function aiEntrySchema(?array $form): array {
			$schema = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");
				$type = (string)($field["type"] ?? "text");

				if ($column === "" || !in_array($type, self::AI_SIMPLE_FIELD_TYPES, true)) {

					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$schema[$column] = [
					"column" => $column,
					"type" => $type,
					"title" => (string)($field["title"] ?? $column),
					"required" => !empty($settings["required"]),
				];
			}

			return $schema;
		}

		/**
		 * Keep only the provided values that map to a settable simple field; reject any
		 * column that is a real form field of a complex type the assistant can't set.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $provided
		 * @return array<string,mixed> ["data" => array] or ["error" => string]
		 */
		private function aiSiftEntryData(array $schema, array $provided): array {
			$data = [];

			foreach ($provided as $column => $value) {
				$column = (string)$column;

				if (!isset($schema[$column])) {

					// Unknown columns are ignored (they'd never persist); only surface a
					// column the model likely expected to work but can't.
					continue;
				}

				if (is_array($value)) {

					return ["error" => "Field \"{$column}\" expects a simple value, not a list or object."];
				}

				if ($schema[$column]["type"] === "checkbox") {
					$data[$column] = !empty($value) && $value !== "false" ? "on" : "";
				} else {
					$data[$column] = is_bool($value) ? ($value ? "1" : "0") : (string)$value;
				}
			}

			return ["data" => $data];
		}

		/**
		 * Required schema columns absent (or empty) in the sifted data.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $data
		 * @return list<string>
		 */
		private function aiMissingRequired(array $schema, array $data): array {
			$missing = [];

			foreach ($schema as $column => $field) {
				if (!empty($field["required"]) && (!array_key_exists($column, $data) || $data[$column] === "")) {
					$missing[] = $column;
				}
			}

			return $missing;
		}

		/**
		 * A one-line human description of the settable fields for a schema-discovery
		 * error message.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 */
		private function aiDescribeSchema(array $schema): string {
			if (!$schema) {

				return "(this module form has no fields the assistant can set)";
			}

			$parts = [];

			foreach ($schema as $column => $field) {
				$parts[] = $column . " (" . $field["type"] . ($field["required"] ? ", required" : "") . ")";
			}

			return implode(", ", $parts);
		}

		/**
		 * Field-level preview for a proposal card: the value being set, and (on update)
		 * the value it replaces.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $data
		 * @param array<string,mixed> $existing
		 * @return list<array<string,mixed>>
		 */
		private function aiPreviewEntryData(array $schema, array $data, array $existing = []): array {
			$out = [];

			foreach ($data as $column => $value) {
				$entry = [
					"column" => $column,
					"title" => (string)($schema[$column]["title"] ?? $column),
					"to" => $this->aiPreviewScalar($value),
				];

				if ($existing) {
					$entry["from"] = $this->aiPreviewScalar($existing[$column] ?? "");
				}

				$out[] = $entry;
			}

			return $out;
		}

		/**
		 * @param mixed $value
		 */
		private function aiPreviewScalar($value): string {
			$string = is_scalar($value) ? (string)$value : (string)json_encode($value);

			if (mb_strlen($string) > 200) {
				$string = mb_substr($string, 0, 199) . "…";
			}

			return $string;
		}
	}
