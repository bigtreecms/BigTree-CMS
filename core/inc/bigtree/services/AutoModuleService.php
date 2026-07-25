<?php
	namespace BigTree\Services;

	use BigTree\Api\Hooks;
	use BigTree\Api\Json;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\ModuleEntryToolBackend;
	use BigTree\Services\AI\ColumnDomain;
	use BigTree\Services\AI\FieldOptionDomain;
	use BigTree\Services\AI\TruncatedRead;
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

		// The markup-bearing subset of the above, whose values are tokenized on the way
		// in. Shared with the page path rather than restated, so the two lists cannot
		// drift apart — see PageService::aiNormalizeHtmlValue.
		private const AI_HTML_FIELD_TYPES = PageService::AI_HTML_RESOURCE_TYPES;

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

			// Create-only, so it sits here rather than inside prepareEntryWrite (which
			// update() shares).
			$this->applyFormDefaultPosition($module, $table, $data);

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
			[$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id, $row]
				= $this->requireEditableEntry($request);

			// A pending entry has no live row yet, so there is nothing to exclude from
			// the uniqueness check; a numeric id excludes its own live row.
			[$data, $mtm, $tags, $og, $publish, $user_level, $can_publish]
				= $this->prepareEntryWrite($request, $module, $table, $is_pending ? 0 : (int)$lookup_id, $row);

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
				// Allocated from the written row, not the request body: a body that
				// doesn't restate every field would otherwise drop the allocations for
				// the fields it omitted.
				$this->trackLiveEntryResources($table, (int)$entry_id);

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
		 * Re-scan a *live* entry's resource allocations from the row as it now stands.
		 *
		 * allocateResources DELETEs every allocation row for (table, entry) before
		 * re-inserting whatever the data it was handed references, so allocating from
		 * a partial change set deletes the allocations for every field the write
		 * didn't mention: an entry whose hero image and PDF weren't part of a title
		 * edit drops both to zero usage in Files, where anyone can then delete them
		 * while they're still live on the site. (With an OG-only edit the change set
		 * is empty, and *every* allocation goes.)
		 *
		 * Read raw rather than through getItem, which untranslates irl:// links into
		 * real URLs that the resource scanner can no longer recognize. This is the
		 * entry-side twin of PageService::finishPageWrite's re-fetch.
		 */
		private function trackLiveEntryResources(string $table, int $entry_id): void {
			$fresh = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $entry_id);

			if (!$fresh) {

				return;
			}

			unset($fresh["id"]);
			$this->trackModuleResources($table, $entry_id, $fresh);
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

			$row = is_array($existing["item"] ?? null) ? $existing["item"] : [];

			PermissionService::assertCanEditRow($request->user, $module, $row);

			return [$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id, $row];
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
		 * $row is the stored row being edited, [] on create (where the submitted data
		 * is itself the prospective row) — it decides which group's rank governs
		 * publishing on a group-based module.
		 * Returns [$data, $mtm, $tags, $og, $publish, $user_level, $can_publish].
		 */
		private function prepareEntryWrite(
			Request $request,
			array $module,
			string $table,
			int $route_exclude_id,
			array $row = []
		): array {
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

			// Per-row rather than best-of-any-group: a user granted `p` on one group
			// and `e` on another must not publish live in the group they only edit.
			$user_level = PermissionService::userEntryLevel($request->user, $module, $row ?: $data);
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

				// On update the stored route is left alone, full stop. The SPA submits
				// the slug it loaded and never regenerates it, so an editor renaming
				// an entry keeps its URL — and it has to, because entries have no
				// route history and no redirect: a regenerated slug 404s every link to
				// the old one, on the site and everywhere off it. (This used to
				// regenerate whenever a source column changed, which made "fix the
				// typo in the headline" silently move the article.)
				//
				// The assistant can't set a route column directly either — `route` is
				// a derived type, excluded from the settable schema — so an entry's
				// URL is only ever changed by a human in the admin, deliberately.
				if (!$is_create) {
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
		 * Seed a new entry's sort column from the form's configured `default_position`.
		 *
		 * The setting has been part of the form schema (ModuleFormService, ModuleService)
		 * and editable in the Module Designer all along, POST/PATCH /modules/{id}/forms
		 * accept it — and nothing on the new API ever applied it. Only the legacy admin
		 * form handler did, so an entry created through REST or by the assistant landed
		 * on the column default instead of the position the form declares, which on a
		 * positioned view is the difference between "new items go to the top" and "new
		 * items go wherever" (audit #9 B4/D4).
		 *
		 * Create-only, and never over a supplied value: reordering an existing entry is
		 * the reorder endpoint's job.
		 *
		 * @param array<string,mixed> $data Mutated in place.
		 */
		private function applyFormDefaultPosition(array $module, string $table, array &$data): void {
			if (array_key_exists("position", $data) && trim((string)$data["position"]) !== "") {

				return;
			}

			$form = $this->formForTable($module, $table);
			$default = $form ? trim((string)($form["default_position"] ?? "")) : "";

			if ($default === "") {

				return;
			}

			// Not every module table is positioned; writing the column blind would fail
			// the INSERT with an opaque SQL error.
			$description = SQL::describeTable($table);

			if (!$description || !isset($description["columns"]["position"])) {

				return;
			}

			$data["position"] = $default;
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

			$sifted = $this->aiSiftEntryData(
				$schema,
				$provided,
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				(string)$table
			);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$data = $sifted["data"];
			$group_error = $this->aiGroupFieldViolation($module, $data, [], $user);

			if ($group_error !== null) {

				return ["error" => $group_error];
			}

			// The proposed data is the prospective row, so a group-based module judges
			// publish rights against the group this entry is actually being filed in.
			$rank = PermissionService::userEntryLevel($user, $module, $data);

			// A publisher can deliberately queue work for someone else's review, the
			// way REST's __publish__ flag lets them. Because a draft is exactly the
			// "a human completes this" case, the blocked-required refusal relaxes to
			// the editor-style warning (aiEntryCreateGate keys off $can_publish).
			$save_as_draft = !empty($args["save_as_draft"]);
			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;
			$name = (string)($module["name"] ?? $module["id"]);
			$blocked = $resolved["blocked_required"];
			$gate = $this->aiEntryCreateGate($resolved, $data, $can_publish);

			if ($gate !== null) {

				return ["error" => $gate];
			}

			// Tagging at create, mirroring create_page's `tags`. Without it an editor
			// couldn't tag a new entry at all: their create lands as pending draft
			// "p{id}", and add_tags only accepts a live entry — so "create this and
			// tag it" couldn't complete until a publisher approved, by which point the
			// tagging intent was lost. Names (not ids) are staged and re-checked at
			// approval, exactly as add_tags and create_page do.
			$tags = new TagService();
			$tag_names = $tags->aiTagNames($args["tags"] ?? []);
			$new_tags = $tag_names ? $tags->aiNewTagNames($tag_names) : [];

			if ($new_tags && PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can create new tags. These don't exist yet: "
					. implode(", ", $new_tags) . ". You can still use tags that already exist."];
			}

			$open_graph = $this->aiEntryOpenGraph($args);
			$relation_error = $this->aiFormRelationError(
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				$tag_names,
				$open_graph
			);

			if ($relation_error !== null) {

				return ["error" => $relation_error];
			}

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: ($save_as_draft
					? " As asked, it will be saved as a draft in the pending queue rather than published — a "
						. "publisher can review and publish it later."
					: " It will be queued as a pending entry for a publisher to review.");

			if ($blocked) {
				$mode_note .= " Note that " . implode(", ", $blocked)
					. " " . (count($blocked) === 1 ? "is required but cannot" : "are required but cannot")
					. " be set by the assistant — a publisher must fill "
					. (count($blocked) === 1 ? "it" : "them") . " in before this entry can go live.";
			}

			// Columns the *table* requires that the form can't fill. In non-strict mode
			// they silently take '' or 0 on every write — the admin's own create does
			// exactly the same thing, because createItem builds its INSERT from the
			// form's columns too, so this is disclosed rather than refused (audit #10
			// A1). Refusing would make the assistant the only writer that can't create
			// an entry on a legacy table.
			$unsettable_columns = ColumnDomain::unsettableRequiredColumns((string)$table, is_array($resolved["form"] ?? null) ? $resolved["form"] : null);

			if ($unsettable_columns) {
				$mode_note .= " The table also has " . (count($unsettable_columns) === 1 ? "a column" : "columns")
					. " the form doesn't cover and the database won't default (" . implode(", ", $unsettable_columns)
					. "), so " . (count($unsettable_columns) === 1 ? "it" : "they") . " will be stored empty — the same "
					. "as any entry added through the admin.";
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

			if ($unsettable_columns) {
				$preview["unsettable_columns"] = $unsettable_columns;
			}

			if ($save_as_draft) {
				$preview["save_as_draft"] = true;
			}

			if ($tag_names) {
				$preview["tags"] = $tag_names;
				$preview["new_tags"] = $new_tags;
			}

			foreach (["title" => "og_title", "description" => "og_description"] as $key => $preview_key) {
				if (isset($open_graph[$key])) {
					$preview[$preview_key] = $open_graph[$key];
				}
			}

			return [
				"ok" => true,
				"summary" => "Create a new entry in the “{$name}” module." . $mode_note,
				"preview" => $preview,
				"payload" => [
					"module_id" => (string)$module["id"],
					"form" => (string)($resolved["form"]["id"] ?? ""),
					"table" => $table,
					"data" => $data,
					"tag_names" => $tag_names,
					"open_graph" => $open_graph,
					"save_as_draft" => $save_as_draft,
				],
			];
		}

		/**
		 * The tags and Open Graph record an AI entry edit has to carry forward.
		 *
		 * updateItem/submitChange treat their $tags and $open_graph arguments as the
		 * complete new set: an empty array deletes the row's tag relations outright
		 * and blanks its Open Graph record. REST can pass [] safely because its client
		 * always submits the whole form body — an AI edit is a partial write of a few
		 * scalar fields, so passing [] there silently destroyed everything the edit
		 * wasn't about.
		 *
		 * $prefer_change reads an outstanding draft's staged values instead of the
		 * live row's, so amending a draft doesn't revert the tags staged on it.
		 *
		 * The same argument applies to the queue row's own `changes` and `mtm_changes`
		 * columns, which submitChange also replaces wholesale — they are returned so
		 * the caller can merge underneath rather than clobber a draft that already has
		 * other fields (and other relations) queued in it.
		 *
		 * @return array{tags:list<int>,open_graph:array<string,mixed>,changes:array<string,mixed>,mtm:array}
		 */
		private function aiExistingEntryRelations(string $table, string $entry_id, bool $is_pending, bool $prefer_change): array {
			$change = null;

			if ($is_pending) {
				$change = SQL::fetch(
					"SELECT changes, mtm_changes, tags_changes, open_graph_changes FROM bigtree_pending_changes WHERE id = ?",
					(int)ltrim($entry_id, "p")
				);
			} elseif ($prefer_change) {
				$change = SQL::fetch(
					"SELECT changes, mtm_changes, tags_changes, open_graph_changes
					 FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?",
					$table,
					(int)$entry_id
				);
			}

			if ($change) {
				$tags = Json::decode($change["tags_changes"]);
				$open_graph = Json::decode($change["open_graph_changes"]);

				return [
					"tags" => array_values(array_map("intval", $tags)),
					"open_graph" => $open_graph,
					"changes" => Json::decode($change["changes"]),
					"mtm" => Json::decode($change["mtm_changes"]),
				];
			}

			// A draft with no change row left (or a live row): fall back to whatever
			// the live row itself carries.
			if ($is_pending) {

				return ["tags" => [], "open_graph" => [], "changes" => [], "mtm" => []];
			}

			$tags = SQL::fetchAllSingle(
				"SELECT tag FROM bigtree_tags_rel WHERE `table` = ? AND entry = ?",
				$table,
				(int)$entry_id
			);
			$og_row = SQL::fetch(
				"SELECT title, description, type, image, image_width, image_height
				 FROM bigtree_open_graph WHERE `table` = ? AND entry = ?",
				$table,
				(int)$entry_id
			);

			return [
				"tags" => array_values(array_map("intval", $tags ?: [])),
				"open_graph" => $og_row ? array_map("strval", $og_row) : [],
				"changes" => [],
				"mtm" => [],
			];
		}

		/**
		 * The staleness descriptor for an entry edit.
		 *
		 * Two holes this closes. An edit bound for a queued draft — which the staging
		 * pass diffs against — has to fingerprint that *draft*, not the live row, or a
		 * wholesale rewrite of the draft slips straight past stalenessError. And an
		 * edit with no column changes at all (an Open Graph-only edit, the commonest
		 * single-field entry edit the assistant makes) produced an empty descriptor,
		 * which is never compared — no staleness protection whatsoever.
		 *
		 * @param int $change_id Non-zero when the target *is* a draft.
		 * @param list<string> $columns
		 * @return array<string,mixed>
		 */
		private function aiEntryFingerprint(
			string $table,
			string $entry_id,
			int $change_id,
			array $columns,
			bool $touches_open_graph
		): array {
			if ($change_id > 0) {

				return ["type" => "pending_change", "id" => $change_id];
			}

			$queued = $this->aiEntryPendingChange($table, (int)$entry_id);
			$parts = $queued ? [["type" => "pending_change", "id" => (int)$queued["id"]]] : [];

			if ($touches_open_graph) {
				$parts[] = ["type" => "open_graph", "table" => $table, "id" => $entry_id];
			}

			if ($columns) {
				$parts[] = ["type" => "entry", "table" => $table, "id" => $entry_id, "columns" => $columns];
			}

			if (!$parts) {

				return [];
			}

			return count($parts) === 1 ? $parts[0] : ["type" => "composite", "parts" => $parts];
		}

		/**
		 * Refuse tags or Open Graph on a form that doesn't offer them.
		 *
		 * A module form opts into each with its own `tagging` / `open_graph` flag, and
		 * FormRenderer only sends `__tags__` / `__open_graph__` when it does. Nothing
		 * on the AI path read those flags, so the assistant could write tag relations
		 * and OG rows against a form whose editor screen has no section for either —
		 * data no admin screen exposes, and none can clean up.
		 *
		 * @param array<string,mixed>|null $form The resolved module form.
		 * @param list<string> $tag_names
		 * @param array<string,mixed> $open_graph
		 */
		private function aiFormRelationError(?array $form, array $tag_names, array $open_graph): ?string {
			if ($tag_names && empty($form["tagging"])) {

				return "This module's form doesn't have tagging enabled, so tags set here would be invisible in the "
					. "admin and unremovable. Turn tagging on for the form in Developer → Modules first.";
			}

			if ($open_graph && empty($form["open_graph"])) {

				return "This module's form doesn't have Open Graph enabled, so a social title or description set "
					. "here would be invisible in the admin and unremovable. Turn Open Graph on for the form in "
					. "Developer → Modules first.";
			}

			return null;
		}

		/**
		 * The outstanding queued change for a live entry, if it has one.
		 *
		 * @return array<string,mixed>|null
		 */
		private function aiEntryPendingChange(string $table, int $entry_id): ?array {
			if ($entry_id < 1) {

				return null;
			}

			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?",
				$table,
				$entry_id
			);

			return $change ?: null;
		}

		/**
		 * What to tell the approver about a queued draft their publish will carry
		 * forward. Empty when there is no draft.
		 *
		 * Publishing an entry deletes its queued change (updateItem), so somebody
		 * else's unreviewed work goes live with the edit — a consequence the approver
		 * has to see on the card rather than discover afterwards.
		 *
		 * @param array<string,mixed>|null $change A bigtree_pending_changes row.
		 * @return array<string,mixed>
		 */
		private function aiEntryDraftDisclosure(?array $change): array {
			if (!$change) {

				return [];
			}

			$owner_id = !empty($change["user"]) ? (int)$change["user"] : 0;
			$owner = $owner_id
				? (string)SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $owner_id)
				: "";
			$fields = array_keys(Json::decode($change["changes"]));

			if (Json::decode($change["tags_changes"] ?? "")) {
				$fields[] = "tags";
			}

			if (Json::decode($change["open_graph_changes"] ?? "")) {
				$fields[] = "open_graph";
			}

			$whose = $owner !== "" ? "{$owner}’s" : "an";

			return [
				"pending_change_id" => (int)$change["id"],
				"owner" => $owner_id ?: null,
				"owner_name" => $owner !== "" ? $owner : null,
				"fields" => array_values(array_unique($fields)),
				"note" => "This entry has {$whose} unpublished draft. Publishing will publish that draft too"
					. ($fields ? " (" . implode(", ", array_unique($fields)) . ")" : "") . ".",
			];
		}

		/**
		 * An entry's effective tag ids: what an outstanding change stages if there is
		 * one, otherwise what is live. The tag tools merge into this set.
		 *
		 * @return list<int>
		 */
		public function aiEntryTagIds(string $table, int $entry_id): array {

			return $this->aiExistingEntryRelations($table, (string)$entry_id, false, true)["tags"];
		}

		// How many entries list_module_entries returns at most. The model can page
		// with `offset`; the payload always says whether there are more.
		private const AI_ENTRY_LIST_CAP = 50;

		// Per-value caps on the two entry read seams. Both used to cut silently — no
		// marker in the payload at all — while update_module_entry replaces the whole
		// column, which is audit #10's B1. get_module_entry now reads at the same
		// budget a page body reads at (PageService::AI_CONTENT_FIELD_CAP): there was
		// never a principled reason for a news body to be shown at one-twelfth of it.
		// list_module_entries stays short — it is a directory, not an editing read —
		// and both now say which fields they cut.
		public const AI_ENTRY_READ_CAP = PageService::AI_CONTENT_FIELD_CAP;

		public const AI_ENTRY_LIST_VALUE_CAP = 300;

		/**
		 * List a module's entries, newest first.
		 *
		 * There was no way to do this at all: search_module_entries requires a query,
		 * sweeps every accessible module, and slices to five rows per module with no
		 * truncation note — so "list the ten most recent news posts" was unanswerable
		 * and the model didn't know it had only seen five. `GET /modules/{id}/entries`
		 * has had this all along.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiListEntries(string $module_id, string $form_id, int $limit, int $offset, $user): array {
			$resolved = $this->aiResolveModuleForm($module_id, $form_id);

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];
			$table = (string)$resolved["table"];

			if (!PermissionService::userHasModuleAccess($user, (string)$module["id"], "v")) {

				return ["denied" => "You do not have permission to view entries in this module."];
			}

			if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !SQL::tableExists($table)) {

				return ["error" => "This module's entry table is not readable."];
			}

			$limit = max(1, min(self::AI_ENTRY_LIST_CAP, $limit));
			$offset = max(0, $offset);
			$schema = $resolved["schema"];
			$filter_rows = !empty($module["gbp"]["enabled"]) && PermissionService::level($user) === 0;

			// One row past the window, so "there are more" is a fact rather than a
			// guess. On a group-based module the per-row filter can eat into the
			// window, so the offset can't be pushed into SQL: over-fetch, filter, and
			// slice here instead.
			if ($filter_rows) {
				$rows = SQL::fetchAll(
					"SELECT * FROM `{$table}` ORDER BY id DESC LIMIT " . (int)(($offset + $limit + 1) * 4)
				);
				$rows = array_values(array_filter($rows, function ($row) use ($user, $module) {

					return PermissionService::userRowLevel($user, $module, is_array($row) ? $row : []) !== "n";
				}));
				$window = array_slice($rows, $offset, $limit + 1);
			} else {
				$window = SQL::fetchAll(
					"SELECT * FROM `{$table}` ORDER BY id DESC LIMIT " . (int)$offset . ", " . (int)($limit + 1)
				);
			}

			$more = count($window) > $limit;
			$window = array_slice($window, 0, $limit);

			// Only the columns the model can actually reason about (and write back),
			// plus the id — a full row dump would be mostly blobs.
			$columns = array_merge(["id"], array_keys($schema));
			// Which values were cut, per row. `has_more` says there are more *rows*; it
			// has never said anything about a row's own values being shown in part, and
			// the write tools replace a column wholesale (audit #10 B1).
			$truncated = [];
			$entries = array_map(function ($row) use ($columns, $schema, &$truncated) {
				$out = [];
				$cut = [];

				foreach ($columns as $column) {
					if (!array_key_exists($column, $row)) {

						continue;
					}

					$value = $row[$column];
					$text = is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value);

					// Decoded on the same terms get_module_entry decodes on, and before the
					// cap for the same reason. Not just for readability: TruncatedRead
					// measures a written-back value against the *decoded* stored value, so a
					// seam that handed back tokens would put every link-bearing field out of
					// that refusal's reach.
					if (in_array((string)($schema[$column]["type"] ?? ""), PageService::AI_LINK_BEARING_TYPES, true)) {
						$text = PageService::aiDenormalizeHtmlValue($text);
					}

					// Cut at exactly the cap, not one short of it: TruncatedRead recognises a
					// written-back value by matching the run of characters the cut ended
					// with, and an off-by-one there would make the refusal miss.
					if (mb_strlen($text) > self::AI_ENTRY_LIST_VALUE_CAP) {
						$text = mb_substr($text, 0, self::AI_ENTRY_LIST_VALUE_CAP) . "…";
						$cut[] = $column;
					}

					$out[$column] = $text;
				}

				$truncated[(string)($row["id"] ?? "")] = $cut;

				return $out;
			}, $window);
			$truncated = array_filter($truncated);

			return [
				"module" => [
					"id" => (string)$module["id"],
					"name" => (string)($module["name"] ?? $module["id"]),
				],
				"form" => (string)($resolved["form"]["id"] ?? ""),
				"table" => $table,
				"offset" => $offset,
				"limit" => $limit,
				"has_more" => $more,
				"entries" => $entries,
				// entry id => the columns shown only in part. A value listed here is not
				// one you can edit — read the entry with get_module_entry first.
				"fields_truncated" => $truncated,
				"value_cap" => self::AI_ENTRY_LIST_VALUE_CAP,
			];
		}

		/**
		 * The read half of what the entry write tools can set beside the row itself:
		 * an entry's tag names and its Open Graph title/description.
		 *
		 * Both are writable through create/update_module_entry and neither appeared in
		 * any read payload, so the model could set a social title but never see one —
		 * and "what is this tagged?" had no answer at all.
		 *
		 * @return array{tags:list<string>,open_graph:array<string,string>}
		 */
		public function aiEntryRelationDetail(string $table, string $entry_id, bool $is_pending): array {
			$relations = $this->aiExistingEntryRelations($table, $entry_id, $is_pending, $is_pending);
			$names = [];

			if ($relations["tags"]) {
				$ids = $relations["tags"];
				$names = SQL::fetchAllSingle(
					"SELECT tag FROM bigtree_tags WHERE id IN (" . Sanitize::placeholders($ids) . ") ORDER BY tag",
					...$ids
				);
			}

			return [
				"tags" => array_values(array_map("strval", $names ?: [])),
				"open_graph" => [
					"og_title" => (string)($relations["open_graph"]["title"] ?? ""),
					"og_description" => (string)($relations["open_graph"]["description"] ?? ""),
				],
			];
		}

		/**
		 * Queue an entry's new tag set as a pending change rather than writing
		 * bigtree_tags_rel live — the tag half of the same non-publisher rule every
		 * other entry write already follows. Everything else already queued on the
		 * change row is carried forward, because submitChange replaces it wholesale.
		 *
		 * @param list<int> $tag_ids The complete resulting tag set.
		 * @param object|array $user
		 */
		public function aiQueueEntryTagChange(string $module_id, string $table, int $entry_id, array $tag_ids, $user): int {
			if (!PermissionService::userHasModuleAccess($user, $module_id, "e")) {
				throw new AuthorizationException("Insufficient module permission to tag entry (e required)");
			}

			$existing = $this->aiExistingEntryRelations($table, (string)$entry_id, false, true);

			$this->bindLegacyAdmin($user);

			$change_id = BigTreeAutoModule::submitChange(
				$module_id,
				$table,
				$entry_id,
				$existing["changes"],
				$existing["mtm"],
				array_values(array_map("intval", $tag_ids)),
				null,
				$existing["open_graph"]
			);

			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
			]);

			return (int)$change_id;
		}

		/**
		 * The assistant's flat og_title/og_description args as the `open_graph` record
		 * the entry write path stores — the same two scalars create_page already
		 * carries, and the same shape BigTreeAdmin::handleOpenGraph reads.
		 *
		 * @param array<string,mixed> $args
		 * @return array<string,string>
		 */
		private function aiEntryOpenGraph(array $args): array {
			$open_graph = [];

			foreach (["og_title" => "title", "og_description" => "description"] as $arg => $key) {
				$value = trim((string)($args[$arg] ?? ""));

				if ($value !== "") {
					$open_graph[$key] = $value;
				}
			}

			return $open_graph;
		}

		/**
		 * Resolve an AI-supplied entry id, which may address a pending ("p"-prefixed)
		 * draft rather than a live row.
		 *
		 * The assistant's own editor-level creates land in the pending queue, so
		 * without this the assistant could create a draft and then be unable to fix it
		 * — "actually, change the phone number on that draft" had no path, and the id
		 * scheme was invisible to the model so it couldn't explain why. REST has
		 * addressed pending entries all along (see parseEntryId / requireEditableEntry);
		 * this is the same addressing for the tool seam, minus the exceptions.
		 *
		 * Public because the *read* seam needs it too: get_module_entry casting to int
		 * turned "p12" into 0, so the model could edit a draft it could never look at
		 * (SearchService::getModuleEntryDetail, mirroring how getPageDetail reuses
		 * PageService::aiResolvePageTarget).
		 *
		 * @return array{error?:string,is_pending?:bool,lookup_id?:string,change_id?:int,row?:array<string,mixed>}
		 */
		public function aiResolveEntryRow(string $table, string $raw): array {
			$raw = trim($raw);

			if ($raw === "") {

				return ["error" => "An entry_id is required."];
			}

			if (strlen($raw) > 1 && $raw[0] === "p" && ctype_digit(substr($raw, 1))) {
				$is_pending = true;
				$lookup_id = $raw;
				$change_id = (int)substr($raw, 1);
			} elseif (ctype_digit($raw) && (int)$raw > 0) {
				$is_pending = false;
				$lookup_id = (string)(int)$raw;
				$change_id = 0;
			} else {

				return ["error" => "\"{$raw}\" isn't an entry id. Use the numeric id of a live entry, or a "
					. "\"p\"-prefixed id (like \"p12\") for an entry that is still an unpublished draft."];
			}

			// Only a draft is read through getPendingItem. For a live id it would
			// overlay any outstanding draft onto the published row, and every caller
			// here acts on the *published* row: the proposal diff would show the
			// draft's values as its "from", set_module_entry_flag would read the
			// draft's flag while writing the live column, and — worst — the per-row
			// GBP check would run against an unpublished group value, granting access
			// the published row doesn't.
			$item = $is_pending
				? BigTreeAutoModule::getPendingItem($table, $lookup_id)
				: BigTreeAutoModule::getItem($table, (int)$lookup_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$row) {

				return ["error" => "Entry {$raw} does not exist in this module."];
			}

			return ["is_pending" => $is_pending, "lookup_id" => $lookup_id, "change_id" => $change_id, "row" => $row];
		}

		/**
		 * Refuse a write that would file an entry in a group the user has no grant on.
		 *
		 * Nothing validated a *new* `group_field` value: the permission pre-check runs
		 * against the row as it stands, so an editor could move an entry into a group
		 * they have no access to (or, on create, file it straight into one). Nothing
		 * marks the column special either, so the model will happily set it. Shared by
		 * staging and approval.
		 *
		 * @param array<string,mixed> $module
		 * @param array<string,mixed> $data The sifted changes.
		 * @param array<string,mixed> $row  The stored row, [] on create.
		 * @return string|null An error message, or null when the write is acceptable.
		 */
		private function aiGroupFieldViolation(array $module, array $data, array $row, $user): ?string {
			$gbp = is_array($module["gbp"] ?? null) ? $module["gbp"] : [];
			$group_field = (string)($gbp["group_field"] ?? "");

			if (empty($gbp["enabled"]) || $group_field === "") {

				return null;
			}

			// An entry with no group value is filtered out of getFilterQuery for every
			// group-scoped editor, so it is published and then invisible — to the
			// people meant to maintain it, and to the assistant on a later turn. The
			// old guard only ran when the column was already in the write, so a create
			// that simply omitted it sailed through.
			$prospective_group = array_key_exists($group_field, $data)
				? (string)$data[$group_field]
				: (string)($row[$group_field] ?? "");

			if (trim($prospective_group) === "") {

				return "This module uses group-based permissions keyed on \"{$group_field}\", and this entry has no "
					. "value there — it would be published and then invisible to every group-scoped editor. Set "
					. "\"{$group_field}\" and try again.";
			}

			if (!array_key_exists($group_field, $data)) {

				return null;
			}

			// Unchanged is not this write's doing.
			if ($row && (string)($row[$group_field] ?? "") === (string)$data[$group_field]) {

				return null;
			}

			$prospective = array_merge($row, $data);

			if (in_array(PermissionService::userRowLevel($user, $module, $prospective), ["e", "p"], true)) {

				return null;
			}

			return "You don't have access to the group this would file the entry in (\"{$group_field}\" = "
				. "\"" . (string)$data[$group_field] . "\"), so it would become an entry you can't edit.";
		}

		/**
		 * The data-validity gate for creating an entry, shared by staging and approval.
		 *
		 * A publisher's write lands straight on the live site, so a form requiring a
		 * field the assistant can't author must be refused outright; a non-publisher's
		 * write only ever reaches the pending queue, where a human completes it, so
		 * that is allowed (and called out in the summary). Because the verdict depends
		 * on the user's rank, and rank can change during a proposal's 24h life, it has
		 * to be re-asked at approval rather than trusted from staging.
		 *
		 * @param array<string,mixed> $resolved The aiResolveModuleForm result.
		 * @param array<string,mixed> $data
		 * @return string|null An error message, or null when the data still passes.
		 */
		private function aiEntryCreateGate(array $resolved, array $data, bool $can_publish): ?string {
			$missing = $this->aiMissingRequired($resolved["schema"], $data);

			if ($missing) {

				return "These required fields are missing: " . implode(", ", $missing) . ".";
			}

			$blocked = is_array($resolved["blocked_required"] ?? null) ? $resolved["blocked_required"] : [];

			if ($blocked && $can_publish) {

				return "This module requires fields the assistant can't fill in: "
					. implode(", ", $blocked) . ". Create this entry in the admin UI instead.";
			}

			return null;
		}

		/**
		 * The data-validity gate for *editing* an entry, shared by staging and approval.
		 *
		 * Create ran a required-field check; update ran none at all, so setting a
		 * required column to "" sifted cleanly, staged, and published live for a
		 * publisher. The page-content path solved the same problem by merging the
		 * stored row with the proposed changes and re-gating (aiMergePageContent);
		 * this is that check for entries.
		 *
		 * Only columns the edit actually touches are judged. A row that was already
		 * missing a required value — imported, or made required after the fact — is
		 * not this edit's doing, and refusing every unrelated change to it would make
		 * those rows uneditable through the assistant rather than fixable.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $row The stored entry.
		 * @param array<string,mixed> $data The sifted changes.
		 * @return string|null An error message, or null when the edit still passes.
		 */
		private function aiEntryUpdateGate(array $schema, array $row, array $data): ?string {
			$merged = array_merge($row, $data);
			$blanked = [];

			foreach ($schema as $id => $field) {
				if (empty($field["required"]) || !array_key_exists($id, $data)) {

					continue;
				}

				$value = $merged[$id] ?? "";

				if (is_array($value) ? !$value : trim((string)$value) === "") {
					$blanked[] = (string)($field["title"] ?? $id);
				}
			}

			if (!$blanked) {

				return null;
			}

			return "These fields are required and can't be left empty: " . implode(", ", $blanked) . ".";
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

			// The form is re-resolved before anything is written, both to re-run the
			// gates against it and because `table` is re-derived from the module rather
			// than trusted from the stored payload.
			$resolved = $this->aiResolveModuleForm($module_id, (string)($payload["form"] ?? ""));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])
				|| (string)$resolved["table"] !== $table) {

				return ["mode" => "error", "message" => "This module's forms have changed since this was proposed — ask again."];
			}

			// Re-sifted against the schema as it stands now: a column removed or
			// retyped during the proposal's 24h life must not be written blind.
			$sifted = $this->aiSiftEntryData(
				$resolved["schema"],
				$data,
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				$table
			);

			if (isset($sifted["error"])) {

				return ["mode" => "error", "message" => (string)$sifted["error"]];
			}

			$data = $sifted["data"];
			$group_error = $this->aiGroupFieldViolation($module, $data, [], $user);

			if ($group_error !== null) {

				return ["mode" => "error", "message" => $group_error];
			}

			// Route/geocoding are derived server-side, never supplied by the model.
			// Run them here (at approval) rather than at staging so route uniqueness
			// is evaluated against the table as it stands at write time.
			$this->aiApplyEntryProcessors($module, $table, $data, [], 0);

			// The same seed REST's create() applies — `position` isn't an AI-settable
			// field type, so without this an assistant-created entry on a positioned
			// view ignores the form's configured default.
			$this->applyFormDefaultPosition($module, $table, $data);

			// The data being written is the prospective row, so a group-based module
			// judges publish rights against the group it is actually being filed in.
			$rank = PermissionService::userEntryLevel($user, $module, $data);
			// An explicit "save as draft" forces the pending path however senior the
			// approver is.
			$can_publish = PermissionService::isPublisher($user, $rank) && empty($payload["save_as_draft"]);

			// Re-run the content gate against the approver's *current* rank and the
			// form as it stands now. Staging may have allowed this as a pending draft
			// for an editor; if their rank was raised since, approving it would publish
			// an incomplete record live — refuse instead.
			$gate = $this->aiEntryCreateGate($resolved, $data, $can_publish);

			if ($gate !== null) {

				return ["mode" => "error", "message" => $gate];
			}

			// Tags were staged as names. Re-check the admin gate on any still missing —
			// a tag that existed at staging may have been deleted since — then resolve
			// to the ids the write path expects. On the pending path they ride the
			// change's own tags_changes, which applyPendingChange replays on publish.
			$tag_names = is_array($payload["tag_names"] ?? null) ? $payload["tag_names"] : [];
			$tag_ids = [];

			if ($tag_names) {
				$tags = new TagService();

				if ($tags->aiNewTagNames($tag_names) && PermissionService::level($user) < 1) {
					throw new AuthorizationException("Only administrators can create new tags");
				}

				$tag_ids = $tags->aiResolveTagIds($tag_names);
			}

			$open_graph = is_array($payload["open_graph"] ?? null) ? $payload["open_graph"] : [];

			// Re-asked at approval: the form's tagging / Open Graph flags can be turned
			// off inside the proposal's 24h life.
			$relation_error = $this->aiFormRelationError(
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				$tag_names,
				$open_graph
			);

			if ($relation_error !== null) {

				return ["mode" => "error", "message" => $relation_error];
			}

			$this->bindLegacyAdmin($user);

			// The same normalization PendingChangeService runs before the identical
			// write. Without it a publisher's create hit strict-mode MySQL with a
			// model-shaped date ("March 3rd, 2027") and failed, while the *same*
			// proposal approved by an editor queued, got sanitized at publish, and
			// worked — the outcome depended on who clicked Approve.
			$data = BigTreeAutoModule::sanitizeData($table, $data);

			if ($can_publish) {
				$id = BigTreeAutoModule::createItem($table, $data, [], $tag_ids, null, $open_graph);

				// createItem returns false on a failed query. Casting that to 0 told the
				// user their entry was published, with entry_id 0 and a resource
				// allocation written against entry 0.
				if (!$id) {

					return ["mode" => "error", "message" => "The entry could not be saved — the database rejected the "
						. "values. Check the field values and try again."];
				}

				$this->trackModuleResources($table, (int)$id, $data);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "via" => "ai_assistant",
				]);

				return ["mode" => "published", "module" => $module_id, "entry_id" => (int)$id];
			}

			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $table, $data, [], $tag_ids, null, false, $open_graph
			);
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

			$entry_id = trim((string)($args["entry_id"] ?? ""));

			if ($entry_id === "") {

				return ["error" => "An entry_id is required to edit a module entry."];
			}

			if (!PermissionService::userHasModuleAccess($user, $module["id"], "e")) {

				return ["denied" => "You do not have permission to edit entries in this module."];
			}

			$resolved_entry = $this->aiResolveEntryRow($table, $entry_id);

			if (isset($resolved_entry["error"])) {

				return $resolved_entry;
			}

			$row = $resolved_entry["row"];
			$is_pending = $resolved_entry["is_pending"];

			// Per-row group-based-permission check (assertCanEditRow's non-throwing
			// core). Deliberately against the *published* row — an unpublished group
			// value must not grant access the live row doesn't.
			if (PermissionService::userRowLevel($user, $module, $row) === "n") {

				return ["denied" => "You do not have permission to edit this specific entry."];
			}

			// Publish rights come from *this row's* rank, not the best of the user's
			// group grants — see PermissionService::userEntryLevel.
			$rank = PermissionService::userEntryLevel($user, $module, $row);
			$save_as_draft = !empty($args["save_as_draft"]);
			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;

			// When this edit is going to join an existing queued draft, that draft is
			// what is being edited — gate and diff against it so the card's `from`
			// values describe what the approver will actually replace. This holds for
			// a publisher too: their approval publishes the draft along with the edit
			// rather than destroying it, so the draft's values are what the change is
			// measured from either way.
			$queued_change = $is_pending ? null : $this->aiEntryPendingChange($table, (int)$entry_id);

			if ($queued_change) {
				$queued_changes = Json::decode($queued_change["changes"]);

				if ($queued_changes) {
					$row = array_merge($row, $queued_changes);
				}
			}

			$provided = is_array($args["data"] ?? null) ? $args["data"] : [];
			$open_graph = $this->aiEntryOpenGraph($args);
			$relation_error = $this->aiFormRelationError(
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				[],
				$open_graph
			);

			if ($relation_error !== null) {

				return ["error" => $relation_error];
			}

			// Open Graph lives beside the row rather than in it, so "just fix the
			// social title" is a legitimate edit with no `data` at all.
			if (!$provided && !$open_graph) {

				return ["error" => "Provide a \"data\" object with the fields to change. Settable fields: "
					. $this->aiDescribeSchema($schema)];
			}

			// $row is the live row overlaid with any queued draft — the values a write
			// would actually replace, and therefore what the truncated-read refusal has
			// to measure against.
			$sifted = $this->aiSiftEntryData(
				$schema,
				$provided,
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				(string)$table,
				is_array($row) ? $row : []
			);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$data = $sifted["data"];

			if (!$data && !$open_graph) {

				return ["error" => "No settable fields were supplied — nothing to update."];
			}

			$group_error = $this->aiGroupFieldViolation($module, $data, $resolved_entry["row"], $user);

			if ($group_error !== null) {

				return ["error" => $group_error];
			}

			$gate = $this->aiEntryUpdateGate($schema, $row, $data);

			if ($gate !== null) {

				return ["error" => $gate];
			}

			// $rank / $save_as_draft / $can_publish are resolved above the overlay,
			// because the overlay decision depends on them.
			$name = (string)($module["name"] ?? $module["id"]);

			// A draft has no live row to publish over: the edit amends the queued
			// change itself, whoever approves it. Saying "published live" there would
			// be a lie, so the note is different.
			if ($is_pending) {
				$mode_note = " It will update the existing draft, which still needs a publisher to approve it.";
			} else {
				$mode_note = $can_publish
					? " It will be published live once you approve."
					: ($save_as_draft
						? " As asked, it will be queued as a pending change rather than published — the live entry "
							. "is untouched until a publisher approves it."
						: " It will be queued as a pending change for a publisher to review.");
			}

			// A publisher's approval carries the queued draft live with it; say whose
			// work that is rather than letting them find out afterwards.
			$draft = $can_publish ? $this->aiEntryDraftDisclosure($queued_change) : [];

			if ($draft) {
				$mode_note .= " " . $draft["note"];
			}

			$label = $is_pending ? "draft {$entry_id}" : "entry #{$entry_id}";

			$preview = [
				"action" => "update_module_entry",
				"module" => $name,
				"entry_id" => $entry_id,
				"is_draft" => $is_pending,
				"fields" => $this->aiPreviewEntryData($schema, $data, $row),
				"mode" => $is_pending ? "pending" : ($can_publish ? "published" : "pending"),
			];

			if ($draft) {
				$preview["publishes_draft"] = $draft;
			}

			if ($save_as_draft && !$is_pending) {
				$preview["save_as_draft"] = true;
			}

			foreach (["title" => "og_title", "description" => "og_description"] as $key => $preview_key) {
				if (isset($open_graph[$key])) {
					$preview[$preview_key] = $open_graph[$key];
				}
			}

			return [
				"ok" => true,
				"summary" => "Update {$label} in the “{$name}” module." . $mode_note,
				"preview" => $preview,
				"payload" => [
					"module_id" => (string)$module["id"],
					"form" => (string)($resolved["form"]["id"] ?? ""),
					"table" => $table,
					"entry_id" => $entry_id,
					"data" => $data,
					"open_graph" => $open_graph,
					"save_as_draft" => $save_as_draft,
				],
				// Only the columns this edit touches: the card's `from` values came
				// from them, so if they moved the card no longer describes the row.
				"fingerprint" => $this->aiEntryFingerprint(
					$table,
					(string)$entry_id,
					$is_pending ? (int)$resolved_entry["change_id"] : 0,
					array_keys($data),
					(bool)$open_graph
				),
				"lock" => $this->aiEntryLock($module, $entry_id, $is_pending),
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
			$raw_entry_id = trim((string)($payload["entry_id"] ?? ""));
			$data = is_array($payload["data"] ?? null) ? $payload["data"] : [];

			if (!PermissionService::userHasModuleAccess($user, $module_id, "e")) {
				throw new AuthorizationException("Insufficient module permission to edit entry (e required)");
			}

			$module = BigTreeJSONDB::get("modules", $module_id);
			$resolved_entry = $this->aiResolveEntryRow($table, $raw_entry_id);

			if (!$module || isset($resolved_entry["error"])) {

				return ["mode" => "error", "message" => "That entry no longer exists."];
			}

			$row = $resolved_entry["row"];
			$is_pending = $resolved_entry["is_pending"];
			$entry_id = $is_pending ? $raw_entry_id : (int)$raw_entry_id;

			PermissionService::assertCanEditRow($user, $module, $row);

			// Re-resolved before anything is written: `table` is re-derived from the
			// module rather than trusted from the stored payload, and the schema below
			// has to be the one that exists now.
			$resolved_form = $this->aiResolveModuleForm($module_id, (string)($payload["form"] ?? ""));

			if (isset($resolved_form["error"]) || !empty($resolved_form["ambiguous_form"])
				|| (string)$resolved_form["table"] !== $table) {

				return ["mode" => "error", "message" => "This module's forms have changed since this was proposed — ask again."];
			}

			// Re-sifted against the schema as it stands now, so a column removed or
			// retyped during the proposal's 24h life isn't written blind.
			$sifted = $this->aiSiftEntryData(
				$resolved_form["schema"],
				$data,
				is_array($resolved_form["form"] ?? null) ? $resolved_form["form"] : null,
				$table,
				is_array($row) ? $row : []
			);

			if (isset($sifted["error"])) {

				return ["mode" => "error", "message" => (string)$sifted["error"]];
			}

			$data = $sifted["data"];
			$group_error = $this->aiGroupFieldViolation($module, $data, $row, $user);

			if ($group_error !== null) {

				return ["mode" => "error", "message" => $group_error];
			}

			// Same derived-field pass as create, but the route is only regenerated
			// when one of its source columns is among the fields being changed —
			// otherwise an unrelated edit would silently re-route the entry. A draft
			// has no live row, so nothing is excluded from the uniqueness check.
			$this->aiApplyEntryProcessors($module, $table, $data, $row, $is_pending ? 0 : (int)$entry_id);

			// Re-gated against the form as it stands now: a column made required during
			// the proposal's 24h life would otherwise be blanked by approving it.
			$gate = $this->aiEntryUpdateGate($resolved_form["schema"], $row, $data);

			if ($gate !== null) {

				return ["mode" => "error", "message" => $gate];
			}

			// Publish rights come from *this row's* rank, not the best of the user's
			// group grants — see PermissionService::userEntryLevel. Judged against the
			// stored row: a user must not gain publish rights by moving a row into a
			// group they happen to publish in.
			$rank = PermissionService::userEntryLevel($user, $module, $row);
			// An explicit "save as draft" forces the pending path however senior the
			// approver is.
			$can_publish = PermissionService::isPublisher($user, $rank) && empty($payload["save_as_draft"]);
			$this->bindLegacyAdmin($user);

			// The write calls below replace the entry's tags and Open Graph wholesale,
			// so an edit that says nothing about them has to hand back what's already
			// there or it deletes them.
			$existing = $this->aiExistingEntryRelations(
				$table,
				(string)$entry_id,
				$is_pending,
				!$can_publish
			);

			// A publisher's approval publishes any queued draft along with the edit
			// rather than destroying it — updateItem deletes the change row either
			// way, so writing only this edit's fields would annihilate the rest of
			// somebody else's draft. Folded in *on top of* the live relations rather
			// than instead of them: a draft that staged no tags, or no Open Graph,
			// must not blank the live ones on its way through.
			$draft = (!$is_pending && $can_publish)
				? $this->aiEntryPendingChange($table, (int)$entry_id)
				: null;

			if ($draft) {
				$existing["changes"] = Json::decode($draft["changes"]);
				$existing["mtm"] = Json::decode($draft["mtm_changes"]);
				$draft_tags = Json::decode($draft["tags_changes"]);
				$draft_og = Json::decode($draft["open_graph_changes"]);

				if ($draft_tags) {
					$existing["tags"] = array_values(array_map("intval", $draft_tags));
				}

				if ($draft_og) {
					$existing["open_graph"] = array_merge($existing["open_graph"], $draft_og);
				}
			}

			// A staged og_title/og_description edits the record rather than replacing
			// it — the image and type the assistant can't author must survive.
			$staged_og = is_array($payload["open_graph"] ?? null) ? $payload["open_graph"] : [];
			$relation_error = $this->aiFormRelationError(
				is_array($resolved_form["form"] ?? null) ? $resolved_form["form"] : null,
				[],
				$staged_og
			);

			if ($relation_error !== null) {

				return ["mode" => "error", "message" => $relation_error];
			}

			$open_graph = $staged_og ? array_merge($existing["open_graph"], $staged_og) : $existing["open_graph"];

			// submitChange replaces the queue row's `changes` and `mtm_changes` blobs
			// outright. REST survives that because its client PATCHes the whole form
			// body, so the replacement is a superset — the assistant hands over only
			// the columns it is changing, so without merging underneath, an AI edit
			// destroys every other field already queued in the same draft. Merged
			// after the processors and the gate so both still judge only this edit.
			$queued = is_array($existing["changes"] ?? null) ? $existing["changes"] : [];
			$write_data = $queued ? array_merge($queued, $data) : $data;
			$mtm = is_array($existing["mtm"] ?? null) ? $existing["mtm"] : [];

			// The same normalization PendingChangeService runs before the identical
			// write, so a model-shaped value doesn't succeed or fail depending on who
			// approves it (an editor's queued write is sanitized at publish either way).
			$data = BigTreeAutoModule::sanitizeData($table, $data);
			$write_data = BigTreeAutoModule::sanitizeData($table, $write_data);

			// A draft only exists in the pending queue — there is nothing to publish
			// over, so even a publisher's edit amends the queued change. submitChange
			// understands the "p" prefix and updates that row in place.
			if ($is_pending) {
				BigTreeAutoModule::submitChange(
					$module_id, $table, $entry_id, $write_data, $mtm, $existing["tags"], null, $open_graph
				);
				$this->trackModuleResources($table, $entry_id, $write_data);
				Hooks::fire("module_entry.pending_updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
				]);

				return ["mode" => "pending", "module" => $module_id, "entry_id" => $entry_id];
			}

			if ($can_publish) {
				// Drop any outstanding draft's allocations before re-scanning the live row.
				$pending_change_id = SQL::fetchSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $table, $entry_id
				);

				if ($pending_change_id) {
					ResourceAllocationService::deallocateResources($table, "p".$pending_change_id);
				}

				// updateItem destroys any queued change for this row, so the draft this
				// edit was merged onto is published with it ($write_data) rather than
				// discarded — the same thing ModuleEntryEdit does by loading the draft
				// and PATCHing it back.
				BigTreeAutoModule::updateItem($table, $entry_id, $write_data, $mtm, $existing["tags"], $open_graph);
				// Allocated from the written row, not this partial change set, which
				// would delete the allocations for every field the edit didn't mention.
				$this->trackLiveEntryResources($table, (int)$entry_id);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
				]);

				return ["mode" => "published", "module" => $module_id, "entry_id" => $entry_id];
			}

			$change_allocation_id = BigTreeAutoModule::submitChange(
				$module_id, $table, $entry_id, $write_data, $mtm, $existing["tags"], null, $open_graph
			);
			$this->trackModuleResources($table, "p".$change_allocation_id, $write_data);
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

			// Flags live on the published row. An unpublished draft has none, so the
			// flag would have nothing to write to — say that rather than failing later.
			if (!empty($resolved["is_pending"])) {

				return ["error" => "{$entry_id} is still an unpublished draft, so it has no {$flag} flag yet. "
					. "It has to be published before its flags can be changed."];
			}

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
					// Carried so approval can re-resolve the same form rather than
					// trusting (or re-guessing) which table this entry lives in.
					"form" => (string)($resolved["form"] ?? ""),
					"table" => $table,
					"entry_id" => $entry_id,
					"flag" => $flag,
					"value" => $value,
				],
				"fingerprint" => ["type" => "entry", "table" => $table, "id" => $entry_id, "columns" => [$flag]],
				"lock" => $this->aiEntryLock($module, $entry_id, false),
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

			if (!$module) {

				return ["mode" => "error", "message" => "That module no longer exists."];
			}

			// The table is re-derived from the module rather than trusted from the
			// stored payload, the way every other entry seam does it.
			$resolved = $this->aiResolveModuleForm($module_id, (string)($payload["form"] ?? ""));

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])
				|| (string)$resolved["table"] !== $table) {

				return ["mode" => "error", "message" => "This module's forms have changed since this was proposed — ask again."];
			}

			$item = BigTreeAutoModule::getItem($table, $entry_id);
			$row = is_array($item) ? ($item["item"] ?? []) : [];

			if (!$row) {

				return ["mode" => "error", "message" => "That entry no longer exists."];
			}

			// The flag column can be dropped from the table during the proposal's life.
			$description = SQL::describeTable($table);

			if (!$description || !isset($description["columns"][$flag])) {

				return ["mode" => "error", "message" => "Entries in this module no longer have a \"{$flag}\" flag."];
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
			$is_pending = !empty($resolved["is_pending"]);

			// Discarding an unpublished draft removes only the queued change; nothing
			// was ever live, so it isn't the same act as deleting a published entry.
			$summary = $is_pending
				? "Discard the unpublished draft “{$label}” ({$entry_id}) in the “{$name}” module. "
					. "Nothing was published, so only the draft is removed."
				: "Permanently delete “{$label}” (entry #{$entry_id}) from the “{$name}” module. "
					. "This cannot be undone.";

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => [
					"action" => "delete_module_entry",
					"module" => $name,
					"entry_id" => $entry_id,
					"entry" => $label,
					"is_draft" => $is_pending,
					"destructive" => true,
				],
				"payload" => [
					"module_id" => (string)$module["id"],
					"form" => (string)($resolved["form"] ?? ""),
					"table" => $resolved["table"],
					"entry_id" => $entry_id,
				],
				// A delete is irreversible, so the whole record is fingerprinted: if
				// anything about it moved, the thing being destroyed isn't the thing
				// the card described.
				"fingerprint" => $is_pending
					? ["type" => "pending_change", "id" => (int)$resolved["change_id"]]
					: ["type" => "entry", "table" => $resolved["table"], "id" => $entry_id,
						"columns" => array_keys($row)],
				"lock" => $this->aiEntryLock($module, $entry_id, $is_pending),
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
			$raw_entry_id = trim((string)($payload["entry_id"] ?? ""));

			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!$module) {

				return ["mode" => "error", "message" => "That module no longer exists."];
			}

			// The table is re-derived from the module rather than trusted from the
			// stored payload — a delete is the last place to guess at which one.
			$resolved_form = $this->aiResolveModuleForm($module_id, (string)($payload["form"] ?? ""));

			if (isset($resolved_form["error"]) || !empty($resolved_form["ambiguous_form"])
				|| (string)$resolved_form["table"] !== $table) {

				return ["mode" => "error", "message" => "This module's forms have changed since this was proposed — ask again."];
			}

			$resolved_entry = $this->aiResolveEntryRow($table, $raw_entry_id);

			if (isset($resolved_entry["error"])) {

				return ["mode" => "error", "message" => "That entry no longer exists (it may already have been deleted)."];
			}

			$row = $resolved_entry["row"];

			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {
				throw new AuthorizationException("Publisher access required to delete an entry");
			}

			$this->bindLegacyAdmin($user);

			// An unpublished draft exists only as a queued change: drop that row and
			// its draft allocations. There is no live entry to delete.
			//
			// Routed through deletePendingItem rather than a raw SQL::delete, which is
			// what DELETE /auto-modules/… uses: a draft is written into the module's
			// view cache on creation (cacheNewItem), and cacheViewData never rebuilds
			// a non-empty view, so deleting only the change row left the module's
			// landing view showing a dead "Pending" row forever. It also writes the
			// audit entry the raw delete skipped.
			if ($resolved_entry["is_pending"]) {
				$change_id = (int)$resolved_entry["change_id"];
				BigTreeAutoModule::deletePendingItem($table, $change_id);
				ResourceAllocationService::deallocateResources($table, "p".$change_id);
				Hooks::fire("module_entry.draft_discarded", [
					"module" => $module_id, "table" => $table, "id" => $raw_entry_id, "via" => "ai_assistant",
				]);

				return ["mode" => "deleted", "module" => $module_id, "entry_id" => $raw_entry_id];
			}

			$entry_id = (int)$raw_entry_id;

			// deleteItem now owns the full teardown — the live row, its resource
			// allocations, its tag relations, its Open Graph row, and the outstanding
			// draft's change row and allocations — and recomputes the affected tags'
			// usage_count. The explicit deallocateResources calls that used to live
			// here duplicated exactly that, so they are gone (audit #7 B3).
			BigTreeAutoModule::deleteItem($table, $entry_id);

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
			$raw_entry_id = trim((string)($args["entry_id"] ?? ""));

			if ($raw_entry_id === "") {

				return ["error" => "An entry_id is required."];
			}

			if (!PermissionService::userHasModuleAccess($user, (string)$module["id"], "e")) {

				return ["denied" => "You do not have permission to change entries in this module."];
			}

			$entry = $this->aiResolveEntryRow($table, $raw_entry_id);

			if (isset($entry["error"])) {

				return $entry;
			}

			// Live rows keep their integer id (callers pass it to SQL); a draft keeps
			// its "p"-prefixed string, which is what the pending-change APIs address.
			return [
				"module" => $module,
				"table" => $table,
				"form" => (string)($resolved["form"]["id"] ?? ""),
				"entry_id" => $entry["is_pending"] ? $entry["lookup_id"] : (int)$entry["lookup_id"],
				"is_pending" => $entry["is_pending"],
				"change_id" => $entry["change_id"],
				"row" => $entry["row"],
			];
		}

		/**
		 * The concurrent-edit lock descriptor for an entry, in the shape
		 * ModuleEntryEdit's useLock call passes ("module:{id}" keyed by entry id).
		 * Empty for a draft: the entry editor locks the live row, and a draft has none.
		 *
		 * @param array<string,mixed> $module
		 * @param int|string $entry_id
		 * @return array<string,mixed>
		 */
		private function aiEntryLock(array $module, $entry_id, bool $is_pending): array {
			$module_id = (string)($module["id"] ?? "");

			// A draft is locked exactly like a live entry: ModuleEntryEdit calls
			// useLock({table: `module:${id}`, itemId: entryId}) with whatever id is in
			// the URL, and a draft's id is the "p"-prefixed one. Bailing on $is_pending
			// meant the one case where two people are most likely to be working the
			// same record — an editor's draft and the publisher reviewing it — was the
			// one case the card said nothing about.
			if ($module_id === "" || (string)$entry_id === "") {

				return [];
			}

			return ["table" => "module:{$module_id}", "id" => (string)$entry_id];
		}

		/**
		 * A human label for an entry in a proposal — the first title-ish column that
		 * has a value, falling back to the id. Approving "delete entry #418" without
		 * knowing what #418 is would be approving blind.
		 *
		 * @param array<string,mixed> $row
		 * @param int|string $entry_id A live row id, or a "p"-prefixed draft id.
		 */
		private function aiEntryLabel(array $row, $entry_id): string {
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
		 * can't be found, or ["ambiguous_form" => true, "forms" => [...]] when the
		 * module has several forms and the caller didn't say which — the tools turn
		 * that into a needs_input question rather than guessing a table.
		 *
		 * Public so the tag tools can route through the same resolution: they write a
		 * relation keyed on the form's table, so picking the first form there would
		 * attach tags to a table the entry doesn't live in.
		 *
		 * @return array<string,mixed>
		 */
		public function aiResolveModuleForm(string $module_id, string $form_id = ""): array {
			if ($module_id === "") {

				return ["error" => "A module_id is required."];
			}

			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!$module) {
				$module = BigTreeJSONDB::get("modules", $module_id, "route");
			}

			if (!$module) {

				return [
					"error" => "Module \"{$module_id}\" does not exist.",
					// "Create a News module and add the first entry" reaches here with
					// the module still on an unapproved card. The wall the model reports
					// should be the sequencing, not a module that "does not exist"
					// (audit #9 A1). The scaffolding decline still applies once the
					// module is real — an AI-created module has no table.
					"prior_change" => [
						"tool" => "create_module",
						"value" => $module_id,
						"keys" => ["name", "route"],
						"label" => "A module called “{$module_id}”",
					],
				];
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
			$gbp = is_array($module["gbp"] ?? null) ? $module["gbp"] : [];
			$group_column = !empty($gbp["enabled"]) ? (string)($gbp["group_field"] ?? "") : "";
			// The table's real column definitions. Everything before audit #10 described
			// the *form* and nothing described the storage, so the model had no way to
			// know a field it was about to fill holds 255 characters — and MySQL, in
			// non-strict mode, wouldn't tell it either.
			$columns = ColumnDomain::columns((string)$resolved["table"]);

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
					// Under group-based permissions this column decides who can see
					// and edit the entry at all. Nothing marked it, so the model
					// treated it as an ordinary field and could publish an entry with
					// it empty — invisible afterwards to every group-scoped editor,
					// including the assistant on a later turn.
					"is_group_column" => $column === $group_column,
					// BigTree's canonical required signal is the `validation` rule
					// string, not the `required` key — read both, or this reports
					// required: false for a field the create gate then rejects.
					"required" => !empty($settings["required"])
						|| in_array("required", $this->aiValidationRules($settings), true),
					"assistant_can_set" => $settable,
					"options" => $settable ? $this->aiFieldOptions($field, $type, $column) : [],
				];

				if (!$settable) {
					$entry["reason"] = $derived
						? "Generated automatically when the entry is saved."
						: "This field type can't be authored by the assistant — it must be filled in the admin UI.";
				}

				if ($settable) {
					$maxlength = (int)($settings["maxlength"] ?? 0);

					if ($maxlength > 0) {
						$entry["maxlength"] = $maxlength;
					}

					$storage = $this->aiColumnStorage($columns[$column] ?? null);

					if ($storage) {
						$entry["storage"] = $storage;
					}
				}

				$fields[] = $entry;
			}

			// Columns the *table* insists on that the form can't fill. Named here as
			// well as on the create proposal, for the reason blocked_required is named
			// here: the model should learn what it can't set before it proposes, not
			// from the card it gets back (audit #10 A1).
			$unsettable_columns = ColumnDomain::unsettableRequiredColumns(
				(string)$resolved["table"],
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null
			);

			return ["schema" => [
				"module_id" => (string)$module["id"],
				"module_name" => (string)($module["name"] ?? $module["id"]),
				"form_id" => (string)($resolved["form"]["id"] ?? ""),
				"table" => $resolved["table"],
				"fields" => $fields,
				"blocked_required" => $resolved["blocked_required"],
				"group_column" => $group_column,
				"group_column_note" => $group_column !== ""
					? "This module uses group-based permissions keyed on \"{$group_column}\". Every entry must "
						. "carry a value there — an entry without one is hidden from every group-scoped editor."
					: "",
				// A view can carry a PHP callback filter that decides, per row, whether an
				// entry appears in the module's landing list at all. An entry created
				// through the form that the view then filters out exists, is correct, is
				// audited — and is invisible to the person who asked for it, who will
				// reasonably report that the assistant did nothing (audit #10 C3).
				"view_filter_note" => $this->aiViewFilterNote($module),
				"unsettable_columns" => $unsettable_columns,
				"unsettable_columns_note" => $unsettable_columns
					? "The table has " . (count($unsettable_columns) === 1 ? "a column" : "columns")
						. " the form doesn't cover and the database won't default, so "
						. (count($unsettable_columns) === 1 ? "it is" : "they are") . " stored empty on every "
						. "entry — the same as one added through the admin. Nothing you can set changes that."
					: "",
				"your_access_level" => PermissionService::userModuleLevel($user, (string)$module["id"]),
			]];
		}

		/**
		 * The storage facts for one column, as the model needs to see them: how much it
		 * holds and, for an enum, what it will accept. Empty when the column tells us
		 * nothing useful (a text blob, or a table we couldn't describe).
		 *
		 * @param array<string,mixed>|null $column A describeTable entry.
		 * @return array<string,mixed>
		 */
		private function aiColumnStorage(?array $column): array {
			if (!$column) {

				return [];
			}

			$type = strtolower((string)($column["type"] ?? ""));
			$storage = [];

			if (($type === "varchar" || $type === "char") && (int)($column["size"] ?? 0) > 0) {
				$storage["max_length"] = (int)$column["size"];
			}

			if (($type === "enum" || $type === "set") && is_array($column["options"] ?? null)) {
				$storage["accepts"] = array_values(array_map("strval", $column["options"]));
			}

			if ($storage) {
				$storage["column_type"] = $type;
			}

			return $storage;
		}

		/**
		 * A note naming the module's primary view filter, when it has one. Shaped like
		 * group_column_note: a fact about visibility the schema payload had no way to
		 * express, stated once rather than discovered after a write.
		 *
		 * @param array<string,mixed> $module
		 */
		private function aiViewFilterNote(array $module): string {
			foreach ((array)($module["views"] ?? []) as $view) {
				if (!is_array($view)) {

					continue;
				}

				$settings = is_array($view["settings"] ?? null) ? $view["settings"] : [];
				$filter = trim((string)($settings["filter"] ?? ""));

				if ($filter === "") {

					continue;
				}

				$title = (string)($view["title"] ?? $view["id"] ?? "");

				return "The " . ($title !== "" ? "“{$title}”" : "module's") . " view runs a per-row filter callback "
					. "(\"{$filter}\"), so a new entry may not appear in the module's list even though it was saved "
					. "correctly. Say so if the user reports not seeing it.";
			}

			return "";
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

				// A field made required through the `validation` rule string — which is
				// how every pre-SPA module spells it — was invisible here, so
				// blocked_required came back empty and aiEntryCreateGate let a
				// publisher publish an entry the admin's own validator refuses.
				$required = !empty($settings["required"])
					|| in_array("required", $this->aiValidationRules($settings), true);

				if ($column === "" || !$required) {
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

				// The form editor stores rules as a whitespace list ("required email")
				// in `validation`, which FieldProcessingService enforces at write time.
				// Only `required` was read here, and only from its own key — so a field
				// made required through the rule string wasn't gated at all, and
				// numeric/email/link were never checked before the write refused them.
				$rules = $this->aiValidationRules($settings);

				$schema[$column] = [
					"column" => $column,
					"type" => $type,
					"title" => (string)($field["title"] ?? $column),
					"required" => !empty($settings["required"]) || in_array("required", $rules, true),
					"rules" => array_values(array_diff($rules, ["required"])),
					// The option set, when there is one. Neither schema emitted it and
					// the sift accepted any string, so a `db`-populated list — whose
					// stored value is a *foreign row id* — silently took the label the
					// model wrote and resolved to blank everywhere it was rendered.
					"options" => $this->aiFieldOptions($field, $type, $column),
					// The field's own character budget. TextField/TextareaField honour
					// this in the browser and nothing on the server ever did, so a field
					// deliberately capped at 60 characters was capped for humans and
					// uncapped for the assistant (audit #10 A3).
					"maxlength" => (int)($settings["maxlength"] ?? 0),
				];
			}

			return $schema;
		}

		/**
		 * The resolved option set for a field that has one, as {value, label}. Empty
		 * for any other field type, and for a list whose options can't be resolved
		 * (a misconfigured `db` list) — that is the admin's problem to report, not a
		 * reason to refuse every write.
		 *
		 * @param array<string,mixed> $field
		 * @return list<array{value:string,label:string}>
		 */
		private function aiFieldOptions(array $field, string $type, string $column): array {

			// One implementation, shared with the page-content path (audit #7 B1), so
			// the two option domains cannot drift apart again.
			return FieldOptionDomain::resolve($field, $type, $column);
		}

		/**
		 * Keep only the provided values that map to a settable simple field; reject any
		 * column that is a real form field of a complex type the assistant can't set.
		 *
		 * Both kinds of rejection used to be a bare `continue`, which is how the
		 * silence happened: a value for a non-required complex field (an upload, a
		 * matrix) or for a misspelled column was dropped without a word, so the model
		 * reported setting it, the user believed the model, and the preview quietly
		 * omitted it. A typo deserves a correction loop, not a no-op.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $provided
		 * @param array<string,mixed>|null $form The resolved form, for naming complex fields.
		 * @param string $table The entry table, for the storage-boundary checks.
		 * @param array<string,mixed> $existing The stored row, for the truncated-read refusal.
		 * @return array<string,mixed> ["data" => array] or ["error" => string]
		 */
		private function aiSiftEntryData(
			array $schema,
			array $provided,
			?array $form = null,
			string $table = "",
			array $existing = []
		): array {
			$data = [];
			$complex = $this->aiComplexEntryColumns($schema, $form);

			foreach ($provided as $column => $value) {
				$column = (string)$column;

				if (!isset($schema[$column])) {
					if (isset($complex[$column])) {

						return ["error" => "Field \"{$column}\" ({$complex[$column]}) can't be set by the assistant — "
							. "it needs the module's own editor in the admin. Settable fields: "
							. $this->aiDescribeSchema($schema)];
					}

					return ["error" => "This form has no field called \"{$column}\". Settable fields: "
						. $this->aiDescribeSchema($schema)];
				}

				if (is_array($value)) {

					return ["error" => "Field \"{$column}\" expects a simple value, not a list or object."];
				}

				if ($schema[$column]["type"] === "checkbox") {
					$data[$column] = !empty($value) && $value !== "false" ? "on" : "";
				} else {
					$data[$column] = is_bool($value) ? ($value ? "1" : "0") : (string)$value;
				}

				// The value as the model wrote it, before tokenization — the form the
				// truncated-read check compares against, because the read decoded too.
				$as_written = $data[$column];

				// Internal links and image sources in AI-authored markup become tokens
				// here, at the sift, so the stored form is the form the approver sees on
				// the card. Shared with the page-content path (audit #9, Part D).
				if (in_array((string)$schema[$column]["type"], self::AI_HTML_FIELD_TYPES, true)) {
					$data[$column] = PageService::aiNormalizeHtmlValue($data[$column]);
				}

				$out_of_domain = $this->aiOptionViolation($schema[$column], $data[$column]);

				if ($out_of_domain !== null) {

					return ["error" => $out_of_domain];
				}

				// `required` is deliberately excluded from these rules and left to the
				// create/update gates, which know whether an empty value is this edit's
				// doing. What's left (numeric, email, link) the write path would refuse
				// outright, so catching it here turns a dead end into a correction.
				$invalid = $this->aiRuleViolation($schema[$column], $data[$column]);

				if ($invalid !== null) {

					return ["error" => $invalid];
				}

				$storage = $this->aiStorageViolation(
					$schema[$column],
					$table,
					$column,
					$data[$column],
					$as_written,
					array_key_exists($column, $existing) && is_scalar($existing[$column])
						? (string)$existing[$column]
						: null
				);

				if ($storage !== null) {

					return ["error" => $storage];
				}
			}

			return ["data" => $data];
		}

		/**
		 * The storage-boundary checks, run on a sifted value once its own field rules
		 * have passed: the field's `maxlength`, characters the connection can't carry,
		 * the column's real width/type/domain, and the truncated-read refusal.
		 *
		 * Everything here is the audit #10 boundary — the point past which MySQL's
		 * non-strict `sql_mode` stops erroring and starts silently changing the data.
		 * The value is passed by reference because the column domain normalizes what
		 * it safely can (a date to the column's format, an enum label to its value)
		 * rather than refusing it.
		 *
		 * @param array<string,mixed> $field The AI schema entry.
		 * @param string $as_written The value before tokenization, as the model wrote it.
		 * @param string|null $stored The value already in this column, when there is one.
		 */
		private function aiStorageViolation(
			array $field,
			string $table,
			string $column,
			string &$value,
			string $as_written,
			?string $stored
		): ?string {
			$title = (string)($field["title"] ?? $column);
			$too_long = ColumnDomain::maxLengthViolation($title, $field["maxlength"] ?? 0, $value);

			if ($too_long !== null) {

				return $too_long;
			}

			$unrepresentable = ColumnDomain::unrepresentable($title, $value);

			if ($unrepresentable !== null) {

				return $unrepresentable;
			}

			if ($table !== "") {
				$violation = ColumnDomain::violation($table, $column, $field, $value);

				if ($violation !== null) {

					return $violation;
				}
			}

			if ($stored !== null) {

				// Compared in the form the read showed it in: link tokens decoded, then
				// capped. Comparing the tokenized forms would miss every body that
				// contains a link, which is most of them.
				if (in_array((string)($field["type"] ?? ""), PageService::AI_LINK_BEARING_TYPES, true)) {
					$stored = PageService::aiDenormalizeHtmlValue($stored);
				}

				return TruncatedRead::violation($title, $as_written, $stored);
			}

			return null;
		}

		/**
		 * Reject a value that isn't one of a list field's options.
		 *
		 * The option set was never exposed and never checked, so the model wrote
		 * whatever read well — which for a `db`-populated list (whose stored value is
		 * a foreign row id) meant the view cache resolved it through
		 * other_table.title_field to nothing at all. A label the user would recognise
		 * is matched back to its value rather than refused outright.
		 *
		 * @param array<string,mixed> $field The schema entry.
		 * @return string|null An error message, or null when the value is acceptable.
		 */
		private function aiOptionViolation(array $field, string &$value): ?string {

			// Shared with the page-content path (audit #7 B1).
			return FieldOptionDomain::violation($field, $value);
		}

		/**
		 * The whitespace-separated rule list a field's settings carry.
		 *
		 * @param array<string,mixed> $settings
		 * @return list<string>
		 */
		private function aiValidationRules(array $settings): array {
			$validation = (string)($settings["validation"] ?? "");

			return preg_split("/\s+/", trim($validation), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		}

		/**
		 * Check one sifted value against its field's non-required rules, using the same
		 * validator the write path uses so the two can't disagree. Returns null when
		 * the value passes.
		 *
		 * @param array<string,mixed> $field The schema entry.
		 * @param mixed $value
		 */
		private function aiRuleViolation(array $field, $value): ?string {
			$rules = is_array($field["rules"] ?? null) ? $field["rules"] : [];

			// An empty optional value has nothing to validate — only `required`, which
			// isn't in this list, has anything to say about emptiness.
			if (!$rules || trim((string)$value) === "") {

				return null;
			}

			$rule_string = implode(" ", $rules);

			if (BigTreeAutoModule::validate($value, $rule_string)) {

				return null;
			}

			$title = (string)($field["title"] ?? $field["column"] ?? "This field");
			// The legacy message opens "This field …"; name the field instead.
			$reason = preg_replace(
				"/^This field /",
				"",
				BigTreeAutoModule::validationErrorMessage($value, $rule_string)
			);

			return "“{$title}” " . $reason . " \"" . $this->aiPreviewScalar($value)
				. "\" would be refused when the entry is saved.";
		}

		/**
		 * The form's real columns that aren't in the settable schema, mapped to a
		 * "Title (type)" label — everything the assistant can see exists but cannot
		 * author. Used to tell a complex field apart from a misspelling.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed>|null $form
		 * @return array<string,string>
		 */
		private function aiComplexEntryColumns(array $schema, ?array $form): array {
			$complex = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");

				if ($column === "" || isset($schema[$column])) {

					continue;
				}

				$type = (string)($field["type"] ?? "");
				$title = (string)($field["title"] ?? $column);
				$complex[$column] = $type !== "" ? "{$title}, {$type}" : $title;
			}

			return $complex;
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
