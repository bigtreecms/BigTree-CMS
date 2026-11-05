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
	use BigTree\Services\AI\FieldDefaultDomain;
	use BigTree\Services\AI\FieldOptionDomain;
	use BigTree\Services\AI\FieldTypeDomain;
	use BigTree\Services\AI\OpenGraphDomain;
	use BigTree\Services\AI\PayloadBudget;
	use BigTree\Services\AI\PreviewValue;
	use BigTree\Services\AI\ResourceReferenceDomain;
	use BigTree\Services\AI\RelationDomain;
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

		// Which field types the assistant may set is no longer a list here: it is
		// derived from the field types' own declared taxonomy by FieldTypeDomain,
		// because the list that used to live at this line named ten types this CMS has
		// never had and omitted one it does. The markup/link-bearing
		// subsets stay on PageService, shared rather than restated, so the two paths
		// cannot tokenize different things.

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

			[$data, $mtm, $tags, $og, $publish, $user_level, $can_publish, $geocoding]
				= $this->prepareEntryWrite($request, $module, $table, 0);

			// Create-only, so they sit here rather than inside prepareEntryWrite (which
			// update() shares).
			$this->applyFormFieldDefaults($module, $table, $data);
			$this->applyFormDefaultPosition($module, $table, $data);

			// The line legacy showed the editor on the save that produced no
			// coordinates. restoreRevision has the same seam; this is the SPA's own entry
			// editor getting it back.
			$warnings = $this->entryWriteWarnings($geocoding);

			// Publishers/admins write live only when they explicitly publish; without
			// the flag they (like editors) save a pending draft.
			if ($can_publish && $publish) {
				$id = BigTreeAutoModule::createItem($table, $data, $mtm, $tags, null, $og);
				$this->trackModuleResources($table, (int)$id, $data);
				$item = BigTreeAutoModule::getItem($table, $id);
				$this->fireFormHooks($module, $table, (int)$id, $data, $mtm, $tags, true);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id, "item" => $item["item"] ?? $item,
				]);

				return $this->auditEntry(
					Response::created($this->withEntryWarnings($item["item"] ?? $item, $warnings), null),
					$table,
					$id
				);
			}

			if ($user_level !== "e" && !$can_publish) {
				throw new AuthorizationException("Editor or publisher access required");
			}

			// createPendingItem reads $admin->ID and tracks the audit entry against
			// the legacy admin global, which doesn't exist in the API request.
			$this->bindLegacyAdmin($request->user);

			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $table, $data, $mtm, $tags, $this->formPublishHook($module, $table), false, $og
			);
			$this->trackModuleResources($table, "p".$pending_id, $data);
			$this->fireFormHooks($module, $table, "p".$pending_id, $data, $mtm, $tags, false);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $table, "pending_id" => (int)$pending_id,
			]);

			return $this->auditEntry(
				Response::created(
					$this->withEntryWarnings(["pending_id" => $pending_id, "pending" => true], $warnings),
					null
				),
				$table,
				"p" . $pending_id
			);
		}

		public function update(Request $request) {
			[$module_id, $raw_id, $module, $table, $is_pending, $lookup_id, $pending_change_id, $row]
				= $this->requireEditableEntry($request);

			// A pending entry has no live row yet, so there is nothing to exclude from
			// the uniqueness check; a numeric id excludes its own live row.
			[$data, $mtm, $tags, $og, $publish, $user_level, $can_publish, $geocoding]
				= $this->prepareEntryWrite($request, $module, $table, $is_pending ? 0 : (int)$lookup_id, $row);
			$warnings = $this->entryWriteWarnings($geocoding);

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
					$this->fireFormHooks($module, $table, $new_id, $data, $mtm, $tags, true);

					return $this->respondUpdated($module_id, $table, $new_id, $warnings);
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
				$this->fireFormHooks($module, $table, $entry_id, $data, $mtm, $tags, true);

				return $this->respondUpdated($module_id, $table, $entry_id, $warnings);
			}

			// submitChange requires a logged-in legacy admin ($admin->ID / ->track()),
			// which the API request has no session for — bridge the JWT user in. It
			// understands the "p" prefix, so editing a pending entry updates its
			// existing change rather than creating a second one.
			$this->bindLegacyAdmin($request->user);

			$change_allocation_id = BigTreeAutoModule::submitChange(
				$module_id,
				$table,
				$lookup_id,
				$data,
				$mtm,
				$tags,
				$this->formPublishHook($module, $table),
				$og
			);
			$this->trackModuleResources($table, "p".$change_allocation_id, $data);
			$this->fireFormHooks($module, $table, $raw_id, $data, $mtm, $tags, false);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $raw_id,
			]);

			return $this->auditEntry(
				Response::ok($this->withEntryWarnings(["pending" => true], $warnings)),
				$table,
				$raw_id
			);
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

			$this->fireEntryDeleted($module_id, $table, $raw_id, $is_pending);

			return $this->auditEntry(Response::noContent(), $table);
		}

		/**
		 * Fire the entry-deletion events for one delete, on whichever path ran it.
		 *
		 * The two paths disagreed. REST fired `module_entry.deleted` for a live row and
		 * a discarded draft alike; the AI path fired `module_entry.draft_discarded` for
		 * the draft branch, so an extension listening on `module_entry.deleted` saw a
		 * person discard a draft and never the assistant. The AI name is the better one
		 * — discarding a draft that was never published is a genuinely different act,
		 * and the proposal summary says so — but the fix is to fire both on both paths
		 * rather than to drop either: losing an event breaks extensions, gaining one
		 * doesn't. Spelled here once so the two paths can't drift
		 * again.
		 *
		 * @param int|string $entry_id
		 */
		private function fireEntryDeleted(string $module_id, string $table, $entry_id, bool $was_draft, string $via = ""): void {
			$payload = ["module" => $module_id, "table" => $table, "id" => $entry_id];

			if ($via !== "") {
				$payload["via"] = $via;
			}

			if ($was_draft) {
				Hooks::fire("module_entry.draft_discarded", $payload);
			}

			Hooks::fire("module_entry.deleted", $payload);
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

			return $this->auditEntry(Response::noContent(), $table);
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
			$this->reindexAfterFlag($table, $entry_id, $column);

			Hooks::fire("module_entry.{$column}", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "value" => $next,
			]);

			return $this->auditEntry(
				Response::ok([
					"id" => $entry_id,
					"column" => $column,
					"value" => $next,
				]),
				$table,
				$entry_id
			);
		}

		/**
		 * Fill in the `%table%` / `%entry%` tokens the entry routes declare in their
		 * audit block.
		 *
		 * A route declaration is a static array: it can name the module id, but not
		 * the table the row lives in, which is resolved per request from the module's
		 * view or form. So every entry write audited under the literal "module_entry"
		 * — a name the audit screen's table picker can never offer, shared across
		 * every module, and not the name the assistant's own writes use. Naming the
		 * real table here is what makes "what happened to entry 12 in News?" one
		 * question with one answer.
		 *
		 * @param int|string|null $entry The row actually written ("p12" for a draft).
		 */
		private function auditEntry(Response $response, string $table, $entry = null): Response {
			$response->audit["table"] = $table;

			if ($entry !== null) {
				$response->audit["entry"] = (string)$entry;
			}

			return $response;
		}

		/**
		 * Converge the vector index after an archived flip.
		 *
		 * Both flag paths write the column with a raw SQL::update, which bypasses
		 * BigTreeAutoModule::updateItem and therefore its embedding hook — so an
		 * archived entry kept its vector and stayed semantically searchable until
		 * somebody happened to re-save it. indexModuleEntry deletes the vector for an
		 * archived row and rebuilds it for a live one, so one call covers both
		 * directions. approved/featured don't affect indexability, so they don't
		 * pay for a re-embed.
		 *
		 * @param int|string $entry_id
		 */
		private function reindexAfterFlag(string $table, $entry_id, string $column): void {
			if ($column !== "archived") {
				return;
			}

			EmbeddingService::deferIndex(function () use ($table, $entry_id) {
				EmbeddingService::indexModuleEntry($table, $entry_id);
			});
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
		private function respondUpdated(string $module_id, string $table, int $id, array $warnings = []): Response {
			$fresh = BigTreeAutoModule::getItem($table, $id);
			Hooks::fire("module_entry.updated", [
				"module" => $module_id, "table" => $table, "id" => $id, "item" => $fresh["item"] ?? $fresh,
			]);

			return $this->auditEntry(Response::ok($this->withEntryWarnings($fresh, $warnings)), $table, $id);
		}

		/**
		 * The warnings an entry write owes its caller. One list today — the failed
		 * geocode — with the same wording the assistant's approval note carries, so
		 * the two writers say one thing.
		 *
		 * @param array{attempted:bool,ok:bool,error:string,cleared:bool} $report
		 * @return list<string>
		 */
		private function entryWriteWarnings(array $report): array {
			$note = $this->geocodingFailureNote($report);

			return $note !== "" ? [$note] : [];
		}

		/**
		 * Attach them to a write response, when there are any. Same shape as the page
		 * revision restore's: a `warnings` array the SPA raises as a toast
		 * rather than an error, because the entry itself saved.
		 *
		 * Deliberately mixed-in rather than typed: a write's body is whatever its
		 * branch already returned, and this must not be the thing that changes it.
		 *
		 * @param mixed $body
		 * @param list<string> $warnings
		 * @return mixed
		 */
		private function withEntryWarnings($body, array $warnings) {
			if ($warnings && is_array($body)) {
				$body["warnings"] = $warnings;
			}

			return $body;
		}

		/**
		 * The form publish-hook string for pending changes (so dashboard approve
		 * can fire it later). Null when the module form has none.
		 *
		 * Every writer of a pending change has to pass this — it is persisted into
		 * `bigtree_pending_changes.publish_hook` and is the only copy anything has.
		 * A change queued with null loses its publish hook permanently: the SPA, the
		 * REST approve route and publish_pending_change all read the stored value
		 * (PendingChangeService::applyPendingChange), so nothing downstream can
		 * recover it.
		 */
		private function formPublishHook(array $module, string $table): ?string {
			$form = $this->formForTable($module, $table);
			$hook = is_array($form) ? trim((string)($form["hooks"]["publish"] ?? "")) : "";

			return $hook !== "" ? $hook : null;
		}

		/**
		 * Fire module-form post/publish hooks after an entry write.
		 *
		 * Mirrors the legacy auto-modules form process path and the public embed
		 * form path in ModuleService: post always runs; publish runs only when the
		 * row landed live (not a pending draft). Extensions like Events rely on
		 * publish hooks to rebuild caches.
		 *
		 * Called from every writer of a module entry — REST's create()/update() and the
		 * AI seams aiCreateEntry()/aiUpdateEntry() — because a hook that fires from
		 * only one of them is a divergence, and AIWriteSideEffectParityTest holds it
		 * shut. A throwing hook is logged and swallowed: the row is already written,
		 * and failing the write over a buggy extension hook invites a retry that
		 * double-writes.
		 *
		 * @param array<string,mixed> $module
		 * @param array<string,mixed> $data
		 * @param array<int|string,mixed> $mtm
		 * @param array<int|string,mixed> $tags
		 * @param int|string $id Live numeric id or "p{n}" pending id
		 */
		private function fireFormHooks(
			array $module,
			string $table,
			$id,
			array $data,
			array $mtm,
			array $tags,
			bool $published
		): void {
			$form = $this->formForTable($module, $table);

			if (!$form) {
				return;
			}

			$hooks = is_array($form["hooks"] ?? null) ? $form["hooks"] : [];

			if (!empty($hooks["post"]) && $this->resolveHookCallable($hooks["post"])) {
				try {
					call_user_func($hooks["post"], $id, $data, $published);
				} catch (\Throwable $e) {
					\BigTree::log("Module form post hook failed for $table/$id: " . $e->getMessage());
				}
			}

			if ($published && !empty($hooks["publish"]) && $this->resolveHookCallable($hooks["publish"])) {
				try {
					call_user_func($hooks["publish"], $table, $id, $data, $mtm, $tags);
				} catch (\Throwable $e) {
					\BigTree::log("Module form publish hook failed for $table/$id: " . $e->getMessage());
				}
			}
		}

		/**
		 * Run the form's `pre` hook over an entry write before anything is processed.
		 *
		 * Legacy's forms/process.php called it with the raw submission, merged the
		 * returned array back over the input, and then copied every returned key no
		 * form field had produced straight onto the entry — which is how a `pre` hook
		 * fills columns the form doesn't show (a computed slug, an author id, a sort
		 * key). The remaster ran it nowhere but embed forms, so every entry written
		 * through REST or by the assistant on such a module was stored without those
		 * columns.
		 *
		 * Here both legs collapse into one merge, done *before* the field processors:
		 * the processors still win over the hook on the columns they derive (legacy's
		 * "only if no field set it"), and createItem only ever writes real columns.
		 * The returned keys are filtered to real columns and form columns anyway, so a
		 * queued change never carries junk, and `id` is never taken — the primary key
		 * comes from the route, as prepareEntryWrite already insists.
		 *
		 * $input is what the hook is shown; it defaults to $data. An AI update passes
		 * the stored row merged with the edit, because legacy's hook always saw the
		 * whole form and the assistant's edit is partial. Only the keys the hook
		 * returns are folded back into $data.
		 *
		 * A throwing hook is logged and swallowed, the same as fireFormHooks: the hook
		 * is developer code and must not be able to fail the save.
		 *
		 * @param array<string,mixed> $module
		 * @param array<string,mixed> $data
		 * @param array<string,mixed>|null $input
		 * @return array{0: array<string,mixed>, 1: list<string>} the data with the
		 *         hook's output folded in, and the columns whose value the hook changed
		 */
		private function applyFormPreHook(array $module, string $table, array $data, ?array $input = null): array {
			$form = $this->formForTable($module, $table);
			$hook = is_array($form["hooks"] ?? null) ? ($form["hooks"]["pre"] ?? null) : null;

			if (empty($hook) || !$this->resolveHookCallable($hook)) {

				return [$data, []];
			}

			$input = $input ?? $data;

			try {
				$output = call_user_func($this->resolveHookCallable($hook), $input);
			} catch (\Throwable $e) {
				\BigTree::log("Module form pre hook failed for $table: " . $e->getMessage());

				return [$data, []];
			}

			if (!is_array($output) || !$output) {

				return [$data, []];
			}

			$description = SQL::describeTable($table);
			$columns = is_array($description["columns"] ?? null) ? $description["columns"] : [];
			$form_columns = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");

				if ($column !== "") {
					$form_columns[$column] = true;
				}
			}

			$changed = [];

			foreach ($output as $key => $value) {
				$key = (string)$key;

				if ($key === "id" || (!isset($columns[$key]) && !isset($form_columns[$key]))) {
					continue;
				}

				if (!array_key_exists($key, $input) || $input[$key] !== $value) {
					$changed[] = $key;
				}

				$data[$key] = $value;
			}

			return [$data, $changed];
		}

		/**
		 * The names of the hooks a form declares, in a fixed order.
		 *
		 * @param array<string,mixed>|null $form
		 * @return list<string>
		 */
		private function aiFormHookNames(?array $form): array {
			$hooks = is_array($form["hooks"] ?? null) ? $form["hooks"] : [];
			$names = [];

			foreach (["edit", "pre", "post", "publish"] as $name) {
				if (!empty($hooks[$name])) {
					$names[] = $name;
				}
			}

			return $names;
		}

		/** Whether this form declares a callable `pre` hook — what the AI cards disclose. */
		private function formHasPreHook(?array $form): bool {
			$hook = is_array($form["hooks"] ?? null) ? ($form["hooks"]["pre"] ?? null) : null;

			return !empty($hook) && $this->resolveHookCallable($hook) !== false;
		}

		/**
		 * The card/summary sentence for a form with a `pre` hook. Staging never runs
		 * the hook — it is arbitrary developer code that may have side effects, and a
		 * staged proposal can be rejected — so the approver is told instead.
		 */
		private const AI_PRE_HOOK_NOTE = "This module's form runs a pre-processing hook when the entry is saved, "
			. "which may set or change values not shown here.";

		/**
		 * The approval-result sentence naming the columns the `pre` hook set.
		 *
		 * @param list<string> $columns
		 */
		private function aiPreHookResultNote(array $columns): string {
			if (!$columns) {

				return "";
			}

			return "The form's pre-processing hook also set " . implode(", ", $columns) . ".";
		}

		/**
		 * Resolve a form-hook value to something is_callable() accepts. Class
		 * autoload is triggered for "Class::method" strings so extension classes
		 * (e.g. BTXEvents::publishHook) load before the check.
		 *
		 * @param mixed $hook
		 * @return callable|false
		 */
		private function resolveHookCallable($hook) {
			if (is_callable($hook)) {
				return $hook;
			}

			if (is_string($hook) && strpos($hook, "::") !== false) {
				[$class, $method] = explode("::", $hook, 2);

				if ($class !== "" && $method !== "" && class_exists($class) && is_callable([$class, $method])) {
					return [$class, $method];
				}
			}

			return false;
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
		 * Returns [$data, $mtm, $tags, $og, $publish, $user_level, $can_publish,
		 * $geocoding] — the last being the geocoding report the response discloses as
		 * `warnings[]` when a lookup failed.
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

			// Before the processors, as legacy ran it: the hook may derive the very
			// columns a route or geocode is computed from.
			[$data] = $this->applyFormPreHook($module, $table, $data);

			$geocoding = $this->applyEntryProcessors($module, $table, $data, $route_exclude_id, $row);

			// Per-row rather than best-of-any-group: a user granted `p` on one group
			// and `e` on another must not publish live in the group they only edit.
			$user_level = PermissionService::userEntryLevel($request->user, $module, $row ?: $data);
			$can_publish = PermissionService::isPublisher($request->user, $user_level);

			return [$data, $mtm, $tags, $og, $publish, $user_level, $can_publish, $geocoding];
		}

		/**
		 * Run every server-side entry field processor over $data before it is
		 * persisted. Request-free so both the REST write path (prepareEntryWrite)
		 * and the AI execute path (aiCreateEntry/aiUpdateEntry) share one
		 * implementation — the AI path skipped these entirely before, producing
		 * entries with no route and no coordinates.
		 *
		 * $route_exclude_id is the live row id to exclude from the route-uniqueness
		 * check (0 on create). $row is the stored row being edited ([] on create),
		 * which is what a failed geocode decides against — see applyGeocoding.
		 *
		 * Returns applyGeocoding's report, which both writers disclose rather than
		 * discard.
		 *
		 * @param array<string,mixed> $row
		 * @return array{attempted:bool,ok:bool,error:string,cleared:bool}
		 */
		private function applyEntryProcessors(
			array $module,
			string $table,
			array &$data,
			int $route_exclude_id,
			array $row = []
		): array {
			$report = $this->applyGeocoding($module, $table, $data, $row);
			$this->applyRoute($module, $table, $data, $route_exclude_id);

			return $report;
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
		 * @return array{attempted:bool,ok:bool,error:string,cleared:bool} the geocoding report
		 */
		private function aiApplyEntryProcessors(array $module, string $table, array &$data, array $row, int $exclude_id): array {
			$report = ["attempted" => false, "ok" => false, "error" => "", "cleared" => false];
			$form = $this->formForTable($module, $table);

			if (!$form) {

				return $report;
			}

			$is_create = !$row;
			$merged = array_merge($row, $data);
			$derived = [];
			$geocodes = false;

			foreach ((array)($form["fields"] ?? []) as $field) {
				$type = (string)($field["type"] ?? "");

				if ($type === "geocoding") {
					$geocodes = true;

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

				// The model can't supply a route, so a value here came from the form's
				// `pre` hook — kept, as legacy's route processor kept a submitted one.
				if (trim((string)($merged[$column] ?? "")) !== "") {
					continue;
				}

				$merged[$column] = "";
				$derived[] = $column;
			}

			if (!$derived && !$geocodes) {

				return $report;
			}

			$report = $this->applyEntryProcessors($module, $table, $merged, $exclude_id, $row);

			// The coordinates are copied back only when the processor actually
			// decided them — wrote a fresh pair, or cleared a stale one. On a failed
			// lookup that D1 chose to preserve, $merged still holds the live row's
			// values, and copying those back would make the update *re-assert* the
			// previous address's coordinates under the new address rather than simply
			// leaving them be.
			if ($geocodes && ($report["ok"] || $report["cleared"])) {
				$derived = array_merge($derived, self::AI_GEOCODING_COLUMNS);
			}

			foreach ($derived as $column) {
				if (array_key_exists($column, $merged)) {
					$data[$column] = $merged[$column];
				}
			}

			return $report;
		}

		/**
		 * Seed a new entry's untouched columns from their fields' `default` settings.
		 *
		 * The entry-side twin of PageService::normalizePageResources. The SPA's
		 * FormRenderer seeds every form column client-side (`seedValues`, reading the
		 * same `default` setting), so a human-created entry writes all its columns
		 * while a REST- or AI-created one would write only what the caller supplied.
		 * Shared by create() and aiCreateEntry so REST and the assistant get it
		 * together; making only the AI seam do it would reverse the asymmetry rather
		 * than remove it.
		 *
		 * Only columns whose field actually declares a `default` are seeded. Writing
		 * "" into every unsupplied column the way the client does would send an empty
		 * string at a DATE or INT column and fail the INSERT under strict mode.
		 *
		 * Create-only, and never over a supplied value: on an update the stored row
		 * already holds a value, and seeding would overwrite it with the default.
		 *
		 * Also seeds the draw-time defaults (FieldDefaultDomain::implicitDefault),
		 * which legacy applied only by pre-filling the editor.
		 *
		 * @param array<string,mixed> $data Mutated in place.
		 * @return array<string,array{title:string,value:string}> The draw-time defaults
		 *         actually written, for the assistant's result note.
		 */
		private function applyFormFieldDefaults(array $module, string $table, array &$data): array {
			$form = $this->formForTable($module, $table);

			if (!$form || !is_array($form["fields"] ?? null)) {

				return [];
			}

			$defaults = [];

			foreach ($form["fields"] as $field) {
				$column = is_array($field) ? (string)($field["column"] ?? "") : "";

				if ($column === "" || array_key_exists($column, $data)) {

					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				if (array_key_exists("default", $settings) && is_scalar($settings["default"])) {
					$defaults[$column] = $settings["default"];
				}
			}

			// The draw-time defaults legacy applied only by pre-filling the editor.
			// An explicit `default` above always wins, and a column
			// the caller supplied — even as "" — is never touched.
			$implicit = FieldDefaultDomain::implicitDefaults($form["fields"], $data + $defaults, "column");

			foreach ($implicit as $column => $entry) {
				$defaults[$column] = $entry["value"];
			}

			if (!$defaults) {

				return [];
			}

			// Only now worth a SHOW CREATE TABLE. A form field whose column has been
			// dropped from the table would otherwise fail the INSERT with an opaque
			// SQL error — the same reason applyFormDefaultPosition describes first.
			$description = SQL::describeTable($table);
			$columns = is_array($description["columns"] ?? null) ? $description["columns"] : [];

			$written = [];

			foreach ($defaults as $column => $value) {
				if (isset($columns[$column])) {
					$data[$column] = $value;

					if (isset($implicit[$column])) {
						$written[$column] = $implicit[$column];
					}
				}
			}

			return $written;
		}

		/**
		 * The draw-time defaults a create from this form will fill — the same set
		 * applyFormFieldDefaults seeds, computed for the proposal card.
		 *
		 * @param array<string,mixed> $data The values being written.
		 * @return array<string,array{title:string,value:string}>
		 */
		private function aiFormImplicitDefaults(?array $form, array $data): array {
			$fields = is_array($form) && is_array($form["fields"] ?? null) ? $form["fields"] : [];

			return FieldDefaultDomain::implicitDefaults($fields, $data, "column");
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
		 * items go wherever".
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
		 * How applyGeocoding obtains its geocoder.
		 *
		 * A static factory rather than a `new` inline because the failure leg is the
		 * one this seam gets wrong, and it is the leg that has to be testable:
		 * BigTreeGeocoding reaches the network through BigTree::cURL, which has no
		 * stub hook, so pre-seeding the geocode cache covers only the success path.
		 * Null means the real geocoder. A test that sets this resets it in teardown.
		 *
		 * @var callable|null
		 */
		public static $geocoder_factory = null;

		/**
		 * Which stored key each geocoding service's credential lives under, in
		 * `bigtree-internal-geocoding-service`. Read to answer "is a service
		 * configured", never to read a key back out.
		 */
		private const AI_GEOCODING_SERVICE_KEYS = [
			"google" => "google_key",
			"bing" => "bing_key",
			"mapquest" => "mapquest_key",
		];

		/** The longest slice of a geocoder's own error text any disclosure repeats. */
		private const AI_GEOCODER_ERROR_CAP = 200;

		/** The two columns a geocoding field writes. Fixed by the legacy processor. */
		private const AI_GEOCODING_COLUMNS = ["latitude", "longitude"];

		/**
		 * Run server-side field processors over the submitted entry data before it
		 * is persisted. Currently this covers the "geocoding" field, which has no
		 * value of its own: it concatenates its configured source columns into an
		 * address, geocodes it, and writes `latitude` / `longitude` onto the row —
		 * mirroring the legacy geocoding process.php.
		 *
		 * The port used to mirror the *success* path only, and legacy did three
		 * things on failure that it dropped (`field-types/geocoding/process.php`):
		 * it cleared both coordinates, it surfaced the geocoder's own error to the
		 * editor, and — by clearing — it never let a row carry coordinates belonging
		 * to a previous address. Keeping whatever was there meant "move the office to
		 * 123 New Street" wrote the new address under the *old* address's map pin,
		 * with nothing anywhere saying so. And the failure path is the common path,
		 * not the edge: BigTreeGeocoding defaults to Google with whatever `google_key`
		 * is stored, so on an install where Developer → System → Geocoding was never
		 * filled in — the state every fresh install is in — every lookup fails.
		 *
		 * So: a failed lookup clears both columns when the address it
		 * was meant to describe has changed (always true on a create, where there is
		 * nothing to preserve), and leaves them alone when it hasn't — a coordinate
		 * that no longer matches its address is wrong data, one that still matches is
		 * right data a flaky service shouldn't erase. $row is the stored row being
		 * edited, [] on create, and is what "has changed" is judged against.
		 *
		 * Returns a report the callers disclose rather than discard:
		 * `["attempted" => bool, "ok" => bool, "error" => string, "cleared" => bool]`.
		 *
		 * @param array<string,mixed> $row
		 * @return array{attempted:bool,ok:bool,error:string,cleared:bool}
		 */
		private function applyGeocoding(array $module, string $table, array &$data, array $row = []): array {
			$report = ["attempted" => false, "ok" => false, "error" => "", "cleared" => false];
			$form = $this->formForTable($module, $table);

			if (!$form) {

				return $report;
			}

			foreach ((array)($form["fields"] ?? []) as $field) {
				if (!is_array($field) || ($field["type"] ?? "") !== "geocoding") {
					continue;
				}

				$source_fields = self::geocodingSourceColumns($field);
				$location = [];

				foreach ($source_fields as $source_field) {
					if (!isset($data[$source_field])) {
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

				$geocoder = self::geocoder();
				$result = $geocoder->geocode(implode(", ", $location));
				$report["attempted"] = true;

				if ($result) {
					$data["latitude"] = $result["latitude"];
					$data["longitude"] = $result["longitude"];
					$report["ok"] = true;

					continue;
				}

				$report["error"] = self::geocoderErrorText($geocoder);

				if (!$row || self::geocodingSourceChanged($source_fields, $row, $data)) {
					foreach (self::AI_GEOCODING_COLUMNS as $column) {
						$data[$column] = "";
					}

					$report["cleared"] = true;
				}
			}

			return $report;
		}

		/**
		 * The geocoder applyGeocoding will use. Never called anywhere else — the
		 * factory exists for the failure leg's testability, not as a
		 * general injection point.
		 *
		 * @return object an object with geocode(string) and a public $Error
		 */
		private static function geocoder() {
			$factory = self::$geocoder_factory;

			if (is_callable($factory)) {

				return $factory();
			}

			return new \BigTreeGeocoding();
		}

		/**
		 * A geocoding field's configured source columns, de-duplicated and trimmed.
		 * The setting is stored as an array by the Designer and as a comma string by
		 * older installs, exactly as the legacy processor read it.
		 *
		 * @param array<string,mixed> $field
		 * @return list<string>
		 */
		private static function geocodingSourceColumns(array $field): array {
			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$raw = $settings["fields"] ?? [];
			$source_fields = is_array($raw) ? $raw : explode(",", (string)$raw);
			$columns = [];

			foreach ($source_fields as $source_field) {
				$source_field = trim((string)$source_field);

				if ($source_field !== "") {
					$columns[] = $source_field;
				}
			}

			return array_values(array_unique($columns));
		}

		/**
		 * Whether the address this write is storing differs from the one the stored
		 * row already had — the question D1 turns on. A column the write doesn't
		 * mention can't have changed it; a column the row has never held has.
		 *
		 * @param list<string> $columns
		 * @param array<string,mixed> $row
		 * @param array<string,mixed> $data
		 */
		private static function geocodingSourceChanged(array $columns, array $row, array $data): bool {
			foreach ($columns as $column) {
				if (!array_key_exists($column, $data)) {
					continue;
				}

				if (!array_key_exists($column, $row)) {

					return true;
				}

				if (self::geocodingSourceValue($data[$column]) !== self::geocodingSourceValue($row[$column])) {

					return true;
				}
			}

			return false;
		}

		/** One source column's value as the address comparison sees it. */
		private static function geocodingSourceValue($value): string {

			return is_array($value)
				? implode(", ", array_map("strval", $value))
				: trim((string)$value);
		}

		/**
		 * The geocoder's own error, as a disclosure may repeat it.
		 *
		 * Third-party response text, so it is data: collapsed to one
		 * line, capped, and quoted wherever it is shown. Nothing in it is read as an
		 * instruction, and no disclosure ever pairs it with a key.
		 *
		 * @param object $geocoder
		 */
		private static function geocoderErrorText($geocoder): string {
			$error = (is_object($geocoder) && isset($geocoder->Error) && is_scalar($geocoder->Error))
				? trim((string)$geocoder->Error)
				: "";

			if ($error === "") {

				return "";
			}

			return mb_substr(trim((string)preg_replace('/\s+/', " ", $error)), 0, self::AI_GEOCODER_ERROR_CAP);
		}

		/**
		 * Does this form geocode, from which columns into which, and is there a
		 * service configured to do it?
		 *
		 * The one answer the schema, both entry previews and get_module all ask, so
		 * they can't disagree about it. The "configured" half is read from the
		 * internal setting the way BigTreeGeocoding reads it — never through
		 * SystemConfigureService, whose payload carries the API keys themselves and
		 * whose whole route family is declined to the assistant precisely because
		 * integration settings include credentials it must never read back. What is
		 * disclosed is the service's *name* and whether its key is non-empty; the key
		 * itself is never returned, logged or quoted.
		 *
		 * @param array<string,mixed>|null $form
		 * @return array{geocodes:bool,source_columns:list<string>,columns:list<string>,
		 *               service:string,configured:bool}
		 */
		private function aiGeocodingState(?array $form): array {
			$state = [
				"geocodes" => false,
				"source_columns" => [],
				"columns" => self::AI_GEOCODING_COLUMNS,
				"service" => "",
				"configured" => false,
			];

			foreach ((array)($form["fields"] ?? []) as $field) {
				if (!is_array($field) || ($field["type"] ?? "") !== "geocoding") {
					continue;
				}

				$state["geocodes"] = true;
				$state["source_columns"] = array_values(array_unique(array_merge(
					$state["source_columns"],
					self::geocodingSourceColumns($field)
				)));
			}

			if (!$state["geocodes"]) {

				return $state;
			}

			// Named apart from `$settings` on purpose: everywhere else in this service
			// that identifier is a *field's* stored settings blob, and one guard reads
			// this file for exactly that (AIStorageBoundaryTest E1). This is the
			// install's internal geocoding setting, which is a different thing.
			$configured_service = BigTreeCMS::getSetting("bigtree-internal-geocoding-service");
			$configured_service = is_array($configured_service) ? $configured_service : [];
			$service = trim((string)($configured_service["service"] ?? ""));
			$key = (string)(self::AI_GEOCODING_SERVICE_KEYS[$service] ?? "");
			$state["service"] = $service;
			$state["configured"] = $key !== "" && trim((string)($configured_service[$key] ?? "")) !== "";

			return $state;
		}

		/**
		 * The one sentence a schema entry, a card and a proposal summary all use to
		 * say what a geocoding field does to this write — including, when it is the
		 * case, that it will do nothing (disclose, never refuse: an
		 * unconfigured geocoder is a developer setting the editor can't change, and
		 * the entry is a valid entry without coordinates — legacy saved it too).
		 *
		 * @param array{geocodes:bool,source_columns:list<string>,columns:list<string>,
		 *              service:string,configured:bool} $state
		 */
		private function aiGeocodingNote(array $state): string {
			if (empty($state["geocodes"])) {

				return "";
			}

			$from = $state["source_columns"]
				? " from " . $this->aiJoinColumns($state["source_columns"])
				: "";
			$into = $this->aiJoinColumns($state["columns"]);

			if (empty($state["configured"])) {

				return "This form geocodes: {$into} are computed{$from} when the entry is saved. No geocoding "
					. "service is configured under Developer → System → Geocoding, so the lookup will fail and "
					. "they will be stored empty.";
			}

			return "This form geocodes: {$into} are computed{$from} when the entry is saved, by the configured "
				. "{$state["service"]} geocoding service. You can't set them yourself.";
		}

		/**
		 * The columns an entry write fills that the model neither supplies nor sees
		 * in the settable schema.
		 *
		 * `route` and `geocoding` are refused as settable types and are then, quite
		 * correctly, absent from every list the schema carries — including
		 * aiOptionalUnsettableFields, which skips derived types on purpose. The
		 * result was that nothing anywhere named `latitude` / `longitude` or an
		 * entry's URL slug as columns the write would fill, from which source
		 * columns, so the model could not explain to a person why the map pin moved
		 * or didn't, and had no reason to include every part of an address on a
		 * create.
		 *
		 * @param array<string,mixed>|null $form
		 * @param array<string,mixed> $geocoding aiGeocodingState's answer for $form
		 * @return list<array<string,mixed>>
		 */
		private function aiDerivedColumns(?array $form, array $geocoding): array {
			$derived = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				if (!is_array($field) || ($field["type"] ?? "") !== "route") {
					continue;
				}

				$column = (string)($field["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$source = $settings["source"] ?? "";
				$sources = [];

				foreach (is_array($source) ? $source : [$source] as $source_column) {
					$source_column = trim((string)$source_column);

					if ($source_column !== "") {
						$sources[] = $source_column;
					}
				}

				$derived[] = [
					"kind" => "route",
					"columns" => [$column],
					"source_columns" => $sources,
					"note" => $sources
						? "`{$column}` is the entry's URL slug, generated from "
							. $this->aiJoinColumns($sources) . " when the entry is created. It is deliberately "
							. "left alone on every later edit — entries have no route history, so re-slugging "
							. "one would 404 every link to it."
						: "`{$column}` is the entry's URL slug, but the field has no source columns configured, "
							. "so every entry is saved with an empty route and its detail URL 404s. A developer "
							. "has to set the source in Developer → Modules → Module Designer.",
				];
			}

			if (!empty($geocoding["geocodes"])) {
				$derived[] = [
					"kind" => "geocoding",
					"columns" => $geocoding["columns"],
					"source_columns" => $geocoding["source_columns"],
					"note" => $this->aiGeocodingNote($geocoding),
				];
			}

			return $derived;
		}

		/** "`a`", "`a` and `b`", "`a`, `b` and `c`" — column lists, one wording. */
		private function aiJoinColumns(array $columns): string {
			$quoted = [];

			foreach ($columns as $column) {
				$quoted[] = "`" . (string)$column . "`";
			}

			if (count($quoted) < 2) {

				return (string)($quoted[0] ?? "");
			}

			$last = array_pop($quoted);

			return implode(", ", $quoted) . " and " . $last;
		}

		/**
		 * The sentence a failed geocode owes whoever caused the write — the AI
		 * result's `note` at approval, the REST response's `warnings[]` for the SPA's
		 * own editor. Legacy pushed the geocoder's error into $bigtree["errors"] on
		 * the very save that produced no coordinates; this is that line, on both of
		 * the writers that replaced it.
		 *
		 * "" when there is nothing to say: no lookup was attempted, or it worked.
		 *
		 * @param array{attempted:bool,ok:bool,error:string,cleared:bool} $report
		 */
		private function geocodingFailureNote(array $report): string {
			if (empty($report["attempted"]) || !empty($report["ok"])) {

				return "";
			}

			$error = (string)($report["error"] ?? "");
			$because = $error !== "" ? " (the geocoder said “{$error}”)" : "";

			if (!empty($report["cleared"])) {

				return "Geocoding failed{$because}, so this entry's latitude and longitude were stored empty. "
					. "Check the service and key under Developer → System → Geocoding, then re-save the entry.";
			}

			return "Geocoding failed{$because}, so this entry kept the coordinates it already had — its address "
				. "fields didn't change in this edit.";
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
		// as create()/update()), but only the fields FieldTypeDomain classifies as
		// settable, and always two-phase. Access is re-checked here — module edit to
		// stage, publisher to write live, per-row gbp on update — never in the model.

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
				(string)$table,
				[],
				$user
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

			$og_error = OpenGraphDomain::error($args);

			if ($og_error !== null) {

				return ["error" => $og_error];
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
			// form's columns too, so this is disclosed rather than refused. Refusing
			// would make the assistant the only writer that can't create
			// an entry on a legacy table.
			$unsettable_columns = ColumnDomain::unsettableRequiredColumns((string)$table, is_array($resolved["form"] ?? null) ? $resolved["form"] : null);

			if ($unsettable_columns) {
				$mode_note .= " The table also has " . (count($unsettable_columns) === 1 ? "a column" : "columns")
					. " the form doesn't cover and the database won't default (" . implode(", ", $unsettable_columns)
					. "), so " . (count($unsettable_columns) === 1 ? "it" : "they") . " will be stored empty — the same "
					. "as any entry added through the admin.";
			}

			// The columns this write fills from something other than the values on the
			// card. An entry whose coordinates will be empty because no geocoding
			// service is configured is a record stored as complete that isn't, and
			// neither the summary nor the card said a word about it.
			$geocoding_state = $this->aiGeocodingState(is_array($resolved["form"] ?? null) ? $resolved["form"] : null);
			$geocoding_note = $this->aiGeocodingNote($geocoding_state);

			if ($geocoding_note !== "") {
				$mode_note .= " " . $geocoding_note;
			}

			// The hook isn't run here, so what it will do is disclosed.
			$pre_hook_note = $this->formHasPreHook(is_array($resolved["form"] ?? null) ? $resolved["form"] : null)
				? self::AI_PRE_HOOK_NOTE
				: "";

			if ($pre_hook_note !== "") {
				$mode_note .= " " . $pre_hook_note;
			}

			// Fields the create will fill from their draw-time defaults — today's date,
			// a pre-checked box, a list's first option. aiCreateEntry seeds them through
			// applyFormFieldDefaults at approval (so a date is the approval's day), and
			// the card has to say a value is coming that nobody typed.
			$implicit_defaults = $this->aiFormImplicitDefaults(
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				$data
			);

			if ($implicit_defaults) {
				$mode_note .= " " . FieldDefaultDomain::appliedNote($implicit_defaults);
			}

			// Said on the card as well as in the result, because the model
			// is not required to call get_module_schema before a create, and the person
			// clicking Approve is the one who needs to know the entry won't be live.
			$approval_note = $this->aiApprovalNote(
				(string)$table,
				PermissionService::isPublisher($user, $rank),
				true
			);

			// Real, optional fields the assistant can't author — the entry saves fine
			// without them and renders empty on the site, so the card has to say so.
			// Folded into the same `warning` key the approval note uses:
			// both are consequences rather than values, and ProposalCard renders one
			// warning block.
			$unfillable = FieldTypeDomain::optionalUnsettableNote(
				$this->aiOptionalUnsettableFields(is_array($resolved["form"] ?? null) ? $resolved["form"] : null)
			);
			// Only the unconfigured case rises to a warning: "this form geocodes" is
			// information the summary already carries, "it will geocode into nothing"
			// is a consequence the approver is the only person who can act on.
			$geocoding_warning = ($geocoding_state["geocodes"] && !$geocoding_state["configured"])
				? $geocoding_note
				: "";
			$warnings = array_values(array_filter(
				[$approval_note, $unfillable, $geocoding_warning, $pre_hook_note],
				function (string $note): bool {

					return $note !== "";
				}
			));

			$preview = [
				"action" => "create_module_entry",
				"module" => $name,
				"fields" => $this->aiPreviewEntryData($schema, $data, [], $user),
				"mode" => $can_publish ? "published" : "pending",
			];

			if ($warnings) {
				$preview["warning"] = implode(" ", $warnings);
			}

			if ($blocked) {
				$preview["incomplete_required"] = $blocked;
			}

			if ($implicit_defaults) {
				$preview["defaults_applied"] = FieldDefaultDomain::describeApplied($implicit_defaults);
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

			foreach (OpenGraphDomain::FIELDS as $preview_key => $key) {
				if (isset($open_graph[$key])) {
					$preview[$preview_key] = $open_graph[$key];
				}
			}

			$staged = [
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

			// A create has no row to go stale, but it can still be *about* something
			// that does: a file it references can be deleted or replaced inside the
			// proposal's 24h life, and approving would store an id pointing at nothing.
			$resource_ids = ResourceReferenceDomain::referencedIds($schema, $data);

			if ($resource_ids) {
				$staged["fingerprint"] = ["type" => "resources", "ids" => $resource_ids];
			}

			return $staged;
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
		 * @param list<int> $resource_ids Files this edit is about to reference.
		 * @return array<string,mixed>
		 */
		private function aiEntryFingerprint(
			string $table,
			string $entry_id,
			int $change_id,
			array $columns,
			bool $touches_open_graph,
			array $resource_ids = []
		): array {
			// A referenced file is staged as an id, and an id survives its row being
			// deleted, re-uploaded or moved out of view inside the proposal's 24h life.
			// Hashed even when the target is a draft, because the draft
			// descriptor knows nothing about the Files library.
			$reference_part = $resource_ids ? [["type" => "resources", "ids" => $resource_ids]] : [];

			if ($change_id > 0) {
				$draft = ["type" => "pending_change", "id" => $change_id];

				return $reference_part
					? ["type" => "composite", "parts" => array_merge([$draft], $reference_part)]
					: $draft;
			}

			$queued = $this->aiEntryPendingChange($table, (int)$entry_id);
			$parts = $queued ? [["type" => "pending_change", "id" => (int)$queued["id"]]] : [];
			$parts = array_merge($parts, $reference_part);

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
		 * Lay a write's authored many-to-many descriptors over the ones it is carrying
		 * forward, keyed by the (connecting table, my id, other id) triple that
		 * identifies a relationship field.
		 *
		 * An AI edit is a partial write. Passing only what the model authored would
		 * delete every *other* relationship on the entry — the same bug the read-only
		 * case guards against, so authoring must not reintroduce it from the other
		 * side.
		 *
		 * @param list<array<string,mixed>> $existing
		 * @param list<array<string,mixed>> $authored
		 * @return list<array<string,mixed>>
		 */
		private function aiMergeMtm(array $existing, array $authored): array {
			if (!$authored) {

				return $existing;
			}

			$key = function ($entry): string {

				return (string)($entry["table"] ?? "") . "\0" . (string)($entry["my-id"] ?? "")
					. "\0" . (string)($entry["other-id"] ?? "");
			};
			$merged = [];

			foreach ($existing as $entry) {
				if (is_array($entry)) {
					$merged[$key($entry)] = $entry;
				}
			}

			foreach ($authored as $entry) {
				$merged[$key($entry)] = $entry;
			}

			return array_values($merged);
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
			if ($tag_names) {
				$tagging_error = self::aiFormTaggingError($form);

				if ($tagging_error !== null) {

					return $tagging_error;
				}
			}

			if ($open_graph && empty($form["open_graph"])) {

				return "This module's form doesn't have Open Graph enabled, so social sharing values set here "
					. "would be invisible in the admin and unremovable. Turn Open Graph on for the form in "
					. "Developer → Modules first.";
			}

			return null;
		}

		/**
		 * Refuse a tag write against a module form that doesn't have tagging on.
		 *
		 * The tagging half of aiFormRelationError, promoted so the tag tools share the
		 * sentence rather than restating it — the way aiOptionViolation wraps
		 * FieldOptionDomain::violation so two callers cannot drift apart.
		 *
		 * The entry write seams enforce this; add_tags reaches the same
		 * `bigtree_tags_rel` rows without going through the entry form, so it has to
		 * ask too (otherwise the rows it wrote would have no admin screen that could
		 * show or remove them, and the entry's next ordinary save would delete them
		 * outright — auto-modules.php:456 clears the record's relations on every write
		 * and a tagging-off form re-submits none).
		 *
		 * @param array<string,mixed>|null $form The resolved module form.
		 */
		public static function aiFormTaggingError(?array $form): ?string {
			if (!empty($form["tagging"])) {

				return null;
			}

			return "This module's form doesn't have tagging enabled, so tags set here would be invisible in the "
				. "admin and unremovable. Turn tagging on for the form in Developer → Modules first.";
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
		// column. get_module_entry reads at the same budget a page body reads at
		// (PageService::AI_CONTENT_FIELD_CAP): there was never a principled reason for
		// a news body to be shown at one-twelfth of it. list_module_entries stays short
		// — it is a directory, not an editing read — and both now say which fields they
		// cut.
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
			// the write tools replace a column wholesale.
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

			// The per-value cap bounds a column; nothing bounded the payload. Fifty
			// rows of a twenty-five field module at 300 characters each is ~375,000
			// characters in one tool result, re-fed on every later round until the
			// request overflows the model's context and the whole turn dies. Rows are
			// dropped rather than values shortened — a row read whole is
			// worth more than every row read in part — and the drop is disclosed with
			// the `has_more` this payload already carries.
			$fitted = PayloadBudget::fitRows($entries);

			if (count($fitted) < count($entries)) {
				$entries = $fitted;
				$more = true;
				$kept_ids = [];

				foreach ($entries as $entry) {
					$kept_ids[(string)($entry["id"] ?? "")] = true;
				}

				$truncated = array_intersect_key($truncated, $kept_ids);
			}

			return [
				"module" => [
					"id" => (string)$module["id"],
					"name" => (string)($module["name"] ?? $module["id"]),
				],
				"form" => (string)($resolved["form"]["id"] ?? ""),
				"table" => $table,
				"offset" => $offset,
				"limit" => $limit,
				// What this window actually contains, which is not always the limit
				// asked for: the payload budget can end the window early. Paging by
				// `limit` after a short window would step straight over the rows the
				// budget cut, so the model is told the number to advance `offset` by.
				"returned" => count($entries),
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
		 * an entry's tag names, its Open Graph title/description, and its
		 * many-to-many relations.
		 *
		 * Tags and Open Graph are writable through create/update_module_entry and
		 * neither appeared in any read payload, so the model could set a social title
		 * but never see one — and "what is this tagged?" had no answer at all.
		 * Many-to-many joins them because relations are authorable: a
		 * relation lives in a connecting table, not in a column, so it appears in no
		 * part of the flattened row, and an assistant that can set one but not see it
		 * would replace relations it should have left alone.
		 *
		 * @param array<string,mixed>|null $form The resolved form, for its relation fields.
		 * @return array{tags:list<string>,open_graph:array<string,string>,related:array<string,mixed>}
		 */
		public function aiEntryRelationDetail(
			string $table,
			string $entry_id,
			bool $is_pending,
			?array $form = null
		): array {
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
				// All four, keyed by the arguments that write them: the entry read seam is
				// what the surface guard measures the write arguments against, and an
				// entry's social image has to be as readable as a page's.
				"open_graph" => OpenGraphDomain::detail(
					is_array($relations["open_graph"] ?? null) ? $relations["open_graph"] : []
				),
				"related" => $this->aiEntryRelatedIds(
					$form,
					$entry_id,
					is_array($relations["mtm"] ?? null) ? $relations["mtm"] : []
				),
			];
		}

		/**
		 * The related row ids each many-to-many field on this form currently holds,
		 * keyed by the field's column — the same key update_module_entry's `data`
		 * takes, so what the model reads is what it would write.
		 *
		 * A draft's staged relations win over the live ones, matching every other read
		 * on this path: a queued change replaces the entry's relations wholesale, so
		 * the live connecting table is not what the next write is amending.
		 *
		 * @param array<string,mixed>|null $form
		 * @param list<array<string,mixed>> $staged The draft's mtm_changes, when there is one.
		 * @return array<string,list<string>>
		 */
		private function aiEntryRelatedIds(?array $form, string $entry_id, array $staged): array {
			$related = [];
			$by_triple = [];

			foreach ($staged as $entry) {
				if (!is_array($entry)) {

					continue;
				}

				$by_triple[(string)($entry["table"] ?? "") . "\0" . (string)($entry["my-id"] ?? "")
					. "\0" . (string)($entry["other-id"] ?? "")] = array_map(
						"strval",
						is_array($entry["data"] ?? null) ? $entry["data"] : []
					);
			}

			foreach ((array)($form["fields"] ?? []) as $field) {
				if ((string)($field["type"] ?? "") !== "many-to-many") {

					continue;
				}

				$column = (string)($field["column"] ?? "");
				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$connecting = (string)($settings["mtm-connecting-table"] ?? "");
				$my_id = (string)($settings["mtm-my-id"] ?? "");
				$other_id = (string)($settings["mtm-other-id"] ?? "");

				// Identifiers reach SQL below, so they are gated exactly as
				// RelationDomain gates them on the way in.
				if ($column === "" || !preg_match('/^[A-Za-z0-9_]+$/', $connecting)
					|| !preg_match('/^[A-Za-z0-9_]+$/', $my_id) || !preg_match('/^[A-Za-z0-9_]+$/', $other_id)) {

					continue;
				}

				$triple = $connecting . "\0" . $my_id . "\0" . $other_id;

				if (array_key_exists($triple, $by_triple)) {
					$related[$column] = $by_triple[$triple];

					continue;
				}

				$rows = SQL::fetchAllSingle(
					"SELECT `{$other_id}` FROM `{$connecting}` WHERE `{$my_id}` = ? ORDER BY id ASC",
					$entry_id
				) ?: [];
				$related[$column] = array_values(array_map("strval", $rows));
			}

			return $related;
		}

		/**
		 * Queue an entry's new tag set as a pending change rather than writing
		 * bigtree_tags_rel live — the tag half of the same non-publisher rule every
		 * other entry write already follows. Everything else already queued on the
		 * change row is carried forward, because submitChange replaces it wholesale.
		 *
		 * "Everything else" includes the publish hook, and this seam used to pass a
		 * literal null for it — so tagging an entry someone else had a draft queued on
		 * erased the hook that draft stored, and approval is the only thing that could
		 * ever have fired it. The value written back is the form's hook
		 * *as it stands now*, which is what every other write site stores; carrying the
		 * old row's value forward would preserve a stale hook name past a form edit.
		 *
		 * @param list<int> $tag_ids The complete resulting tag set.
		 * @param object|array $user
		 */
		public function aiQueueEntryTagChange(string $module_id, string $table, int $entry_id, array $tag_ids, $user): int {
			if (!PermissionService::userHasModuleAccess($user, $module_id, "e")) {
				throw new AuthorizationException("Insufficient module permission to tag entry (e required)");
			}

			// The module record, for the form the hooks below hang off. Resolved the
			// way aiResolveModuleForm resolves it, because the tag tools accept a route
			// as well as an id.
			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!is_array($module)) {
				$module = BigTreeJSONDB::get("modules", $module_id, "route");
			}

			$module = is_array($module) ? $module : [];
			$existing = $this->aiExistingEntryRelations($table, (string)$entry_id, false, true);
			$tag_ids = array_values(array_map("intval", $tag_ids));

			$this->bindLegacyAdmin($user);

			$change_id = BigTreeAutoModule::submitChange(
				$module_id,
				$table,
				$entry_id,
				$existing["changes"],
				$existing["mtm"],
				$tag_ids,
				$this->formPublishHook($module, $table),
				$existing["open_graph"]
			);

			// The same form hooks every other queueing site fires (:205, :2581, :3064).
			// A tag change is a write to the entry like any other, and the form's `post`
			// hook is how an extension hears about one.
			$this->fireFormHooks($module, $table, $entry_id, $existing["changes"], $existing["mtm"], $tag_ids, false);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
			]);

			return (int)$change_id;
		}

		/**
		 * The assistant's flat og_* args as the `open_graph` record the entry write path
		 * stores — the same record create_page carries, through the same shared map, and
		 * the same shape BigTreeAdmin::handleOpenGraph reads.
		 *
		 * All four authored columns: `type` is a four-value enum and
		 * `image` a URL, and neither is the file reference the old two-field version
		 * claimed as its reason for stopping at title and description.
		 *
		 * @param array<string,mixed> $args
		 * @return array<string,string>
		 */
		private function aiEntryOpenGraph(array $args): array {
			$open_graph = [];

			foreach (OpenGraphDomain::FIELDS as $arg => $key) {
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
				$table,
				[],
				$user
			);

			if (isset($sifted["error"])) {

				return ["mode" => "error", "message" => (string)$sifted["error"]];
			}

			$data = $sifted["data"];
			// Relations the model authored, rebuilt from the form as it stands now
			// — the descriptor's identifiers come from the field
			// definition, so a form edited inside the 24h TTL is re-read here.
			$mtm = is_array($sifted["mtm"] ?? null) ? $sifted["mtm"] : [];
			// The form's `pre` hook, at approval only — staging never runs developer
			// code. It runs before every gate below, so the group check
			// and the required-field gate judge the entry the hook actually produced.
			[$data, $hook_columns] = $this->applyFormPreHook($module, $table, $data);

			if ($hook_columns) {
				// A required column the assistant can't author was refused or disclosed
				// at staging; the hook may have filled it since.
				$resolved["blocked_required"] = $this->aiRequiredUnsettableFields(
					is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
					$data
				);
			}

			$group_error = $this->aiGroupFieldViolation($module, $data, [], $user);

			if ($group_error !== null) {

				return ["mode" => "error", "message" => $group_error];
			}

			// Route/geocoding are derived server-side, never supplied by the model.
			// Run them here (at approval) rather than at staging so route uniqueness
			// is evaluated against the table as it stands at write time.
			$geocoding = $this->aiApplyEntryProcessors($module, $table, $data, [], 0);

			// The same two seeds REST's create() applies — neither a field `default`
			// nor `position` is something the model supplies, so without these an
			// assistant-created entry ignores the form's configured defaults where a
			// human-created one honours them. The draw-time defaults
			// are re-seeded here rather than carried from staging, so a date default is
			// the day the entry is actually created.
			$applied_defaults = $this->applyFormFieldDefaults($module, $table, $data);
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

			// Every gate above has read the relations under their own columns; the row
			// must not carry them.
			$data = $this->aiWithoutMtmColumns($data, $sifted);

			// The same normalization PendingChangeService runs before the identical
			// write. Without it a publisher's create hit strict-mode MySQL with a
			// model-shaped date ("March 3rd, 2027") and failed, while the *same*
			// proposal approved by an editor queued, got sanitized at publish, and
			// worked — the outcome depended on who clicked Approve.
			$data = BigTreeAutoModule::sanitizeData($table, $data);
			// Re-asked at approval like every other staged fact: the column can be added
			// to (or dropped from) the table inside the proposal's 24h life, and this is
			// the sentence that stops "published" being read as "on the site".
			$approval_note = $this->aiApprovalNote(
				$table,
				PermissionService::isPublisher($user, $rank),
				true
			);
			// The lookup the processors just ran, disclosed rather than discarded. It
			// can only be answered here — staging has no way to know whether the
			// geocoder will answer, and the result is the only place the person who
			// clicked Approve finds out their entry has no coordinates.
			$approval_note = $this->aiWithGeocodingNote($approval_note, $geocoding);
			$approval_note = trim($approval_note . " " . $this->aiPreHookResultNote($hook_columns));

			if ($applied_defaults) {
				$approval_note = trim($approval_note . " Filled from the fields' configured defaults: "
					. FieldDefaultDomain::describeApplied($applied_defaults) . ".");
			}

			if ($can_publish) {
				$id = BigTreeAutoModule::createItem($table, $data, $mtm, $tag_ids, null, $open_graph);

				// createItem returns false on a failed query. Casting that to 0 told the
				// user their entry was published, with entry_id 0 and a resource
				// allocation written against entry 0.
				if (!$id) {

					return ["mode" => "error", "message" => "The entry could not be saved — the database rejected the "
						. "values. Check the field values and try again."];
				}

				$this->trackModuleResources($table, (int)$id, $data);
				// The same form hooks REST's create() fires. An extension whose publish
				// hook rebuilds a cache has no other way to hear about an AI-authored
				// entry — the row lands, the derived state doesn't, and the card says
				// "published".
				$this->fireFormHooks($module, $table, (int)$id, $data, $mtm, $tag_ids, true);
				// `item` is the row REST's create() puts in this payload, and the one key
				// a subscriber can't re-derive without a query it shouldn't have to make.
				// A hook file written against the documented REST shape —
				// $data["item"]["title"] — silently no-opped on every assistant-driven
				// write while it was missing.
				$fresh = BigTreeAutoModule::getItem($table, (int)$id);
				Hooks::fire("module_entry.created", [
					"module" => $module_id, "table" => $table, "id" => (int)$id,
					"item" => $fresh["item"] ?? $fresh, "via" => "ai_assistant",
				]);

				return $this->aiWithApprovalNote(
					["mode" => "published", "module" => $module_id, "entry_id" => (int)$id],
					$approval_note
				);
			}

			// The publish hook is stored on the change row, not fired now: whoever
			// approves it later — the SPA, the REST approve route, or the assistant's
			// own publish_pending_change — reads it back off the row. Passing null
			// wrote the omission to the database, where it outlived the proposal.
			$pending_id = BigTreeAutoModule::createPendingItem(
				$module_id, $table, $data, $mtm, $tag_ids, $this->formPublishHook($module, $table), false, $open_graph
			);
			$this->trackModuleResources($table, "p".$pending_id, $data);
			$this->fireFormHooks($module, $table, "p".$pending_id, $data, $mtm, $tag_ids, false);
			Hooks::fire("module_entry.pending_created", [
				"module" => $module_id, "table" => $table, "pending_id" => (int)$pending_id, "via" => "ai_assistant",
			]);

			return $this->aiWithApprovalNote(
				["mode" => "pending", "module" => $module_id, "pending_id" => (int)$pending_id],
				$approval_note
			);
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
			$og_error = OpenGraphDomain::error($args);

			if ($og_error !== null) {

				return ["error" => $og_error];
			}

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
				is_array($row) ? $row : [],
				$user
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

			// Same disclosure as the create card, and it matters more here: on an
			// update a failed lookup used to leave the previous address's coordinates
			// in place under the new address, with nothing saying so.
			$geocoding_state = $this->aiGeocodingState(is_array($resolved["form"] ?? null) ? $resolved["form"] : null);
			$geocoding_note = $this->aiGeocodingNote($geocoding_state);

			if ($geocoding_note !== "") {
				$mode_note .= " " . $geocoding_note;
			}

			// The hook isn't run here, so what it will do is disclosed.
			$has_pre_hook = $this->formHasPreHook(is_array($resolved["form"] ?? null) ? $resolved["form"] : null);

			if ($has_pre_hook) {
				$mode_note .= " " . self::AI_PRE_HOOK_NOTE;
			}

			$label = $is_pending ? "draft {$entry_id}" : "entry #{$entry_id}";

			$preview = [
				"action" => "update_module_entry",
				"module" => $name,
				"entry_id" => $entry_id,
				"is_draft" => $is_pending,
				"fields" => $this->aiPreviewEntryData($schema, $data, $row, $user),
				"mode" => $is_pending ? "pending" : ($can_publish ? "published" : "pending"),
			];

			if ($draft) {
				$preview["publishes_draft"] = $draft;
			}

			// On the edit side: an edit to an entry that is still unapproved changes a
			// record nothing on the site can read, so the card says so. Judged against
			// the stored row rather than the draft overlay — the flag lives on the
			// published row.
			$approval_note = $this->aiApprovalNote(
				(string)$table,
				PermissionService::isPublisher($user, $rank),
				false
			);
			$warnings = [];

			if ($approval_note !== "" && (string)($resolved_entry["row"]["approved"] ?? "") !== "on") {
				$warnings[] = $approval_note;
			}

			if ($geocoding_state["geocodes"] && !$geocoding_state["configured"]) {
				$warnings[] = $geocoding_note;
			}

			if ($has_pre_hook) {
				$warnings[] = self::AI_PRE_HOOK_NOTE;
			}

			if ($warnings) {
				$preview["warning"] = implode(" ", $warnings);
			}

			if ($save_as_draft && !$is_pending) {
				$preview["save_as_draft"] = true;
			}

			foreach (OpenGraphDomain::FIELDS as $preview_key => $key) {
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
				// A many-to-many column is not among them — its value doesn't live in
				// the row, and asking the database for it is either meaningless or an
				// unknown-column error.
				"fingerprint" => $this->aiEntryFingerprint(
					$table,
					(string)$entry_id,
					$is_pending ? (int)$resolved_entry["change_id"] : 0,
					array_keys($this->aiWithoutMtmColumns($data, $sifted)),
					(bool)$open_graph,
					ResourceReferenceDomain::referencedIds($schema, $data)
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
				is_array($row) ? $row : [],
				$user
			);

			if (isset($sifted["error"])) {

				return ["mode" => "error", "message" => (string)$sifted["error"]];
			}

			$data = $sifted["data"];
			// The form's `pre` hook, at approval only. Legacy's hook
			// always saw the whole form; this edit is partial, so it is shown the
			// stored row with the edit over it, and only what it returns is folded back
			// — where the update gate below judges it like any other changed column.
			[$data, $hook_columns] = $this->applyFormPreHook(
				$module,
				$table,
				$data,
				array_merge(is_array($row) ? $row : [], $data)
			);
			$group_error = $this->aiGroupFieldViolation($module, $data, $row, $user);

			if ($group_error !== null) {

				return ["mode" => "error", "message" => $group_error];
			}

			// Same derived-field pass as create, but the route is only regenerated
			// when one of its source columns is among the fields being changed —
			// otherwise an unrelated edit would silently re-route the entry. A draft
			// has no live row, so nothing is excluded from the uniqueness check.
			$geocoding = $this->aiApplyEntryProcessors($module, $table, $data, $row, $is_pending ? 0 : (int)$entry_id);

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
			//
			// Every gate above has read the relations under their own columns; neither
			// the row nor the queued change's blob may carry them.
			$data = $this->aiWithoutMtmColumns($data, $sifted);
			$queued = is_array($existing["changes"] ?? null) ? $existing["changes"] : [];
			$write_data = $queued ? array_merge($queued, $data) : $data;
			// Relations the model authored, laid over the ones this write would
			// otherwise carry forward untouched. Merged by triple so setting one
			// relationship field doesn't blank the entry's other ones — the same
			// reasoning that makes aiExistingEntryRelations necessary, applied now that
			// the assistant can author them too.
			$mtm = $this->aiMergeMtm(
				is_array($existing["mtm"] ?? null) ? $existing["mtm"] : [],
				is_array($sifted["mtm"] ?? null) ? $sifted["mtm"] : []
			);

			// The same normalization PendingChangeService runs before the identical
			// write, so a model-shaped value doesn't succeed or fail depending on who
			// approves it (an editor's queued write is sanitized at publish either way).
			$data = BigTreeAutoModule::sanitizeData($table, $data);
			$write_data = BigTreeAutoModule::sanitizeData($table, $write_data);

			// Re-asked at approval, and only when the row is still unapproved: a
			// published edit to a record the site can't read is not the "it's live now"
			// the result otherwise implies.
			$approval_note = (string)($row["approved"] ?? "") !== "on"
				? $this->aiApprovalNote($table, PermissionService::isPublisher($user, $rank), false)
				: "";
			// …and the same geocoding outcome, on the leg where it matters most: an
			// update whose address moved and whose lookup failed has just had its
			// coordinates cleared, and one whose address didn't has kept them.
			$approval_note = $this->aiWithGeocodingNote($approval_note, $geocoding);
			$approval_note = trim($approval_note . " " . $this->aiPreHookResultNote($hook_columns));

			// A draft only exists in the pending queue — there is nothing to publish
			// over, so even a publisher's edit amends the queued change. submitChange
			// understands the "p" prefix and updates that row in place.
			if ($is_pending) {
				BigTreeAutoModule::submitChange(
					$module_id,
					$table,
					$entry_id,
					$write_data,
					$mtm,
					$existing["tags"],
					$this->formPublishHook($module, $table),
					$open_graph
				);
				$this->trackModuleResources($table, $entry_id, $write_data);
				$this->fireFormHooks($module, $table, $entry_id, $write_data, $mtm, $existing["tags"], false);
				Hooks::fire("module_entry.pending_updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
				]);

				return $this->aiWithApprovalNote(
					["mode" => "pending", "module" => $module_id, "entry_id" => $entry_id],
					$approval_note
				);
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
				$this->fireFormHooks($module, $table, $entry_id, $write_data, $mtm, $existing["tags"], true);
				// The same `item` REST's respondUpdated() carries.
				$fresh = BigTreeAutoModule::getItem($table, $entry_id);
				Hooks::fire("module_entry.updated", [
					"module" => $module_id, "table" => $table, "id" => $entry_id,
					"item" => $fresh["item"] ?? $fresh, "via" => "ai_assistant",
				]);

				return $this->aiWithApprovalNote(
					["mode" => "published", "module" => $module_id, "entry_id" => $entry_id],
					$approval_note
				);
			}

			$change_allocation_id = BigTreeAutoModule::submitChange(
				$module_id,
				$table,
				$entry_id,
				$write_data,
				$mtm,
				$existing["tags"],
				$this->formPublishHook($module, $table),
				$open_graph
			);
			$this->trackModuleResources($table, "p".$change_allocation_id, $write_data);
			$this->fireFormHooks($module, $table, $entry_id, $write_data, $mtm, $existing["tags"], false);
			Hooks::fire("module_entry.pending_updated", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "via" => "ai_assistant",
			]);

			return $this->aiWithApprovalNote(
				["mode" => "pending", "module" => $module_id, "entry_id" => $entry_id],
				$approval_note
			);
		}

		/**
		 * Fold a failed geocode's sentence into the note a write result already owes.
		 *
		 * Both entry writers return through aiWithApprovalNote, which carries exactly
		 * one `note`, so the two facts are composed here rather than competing for the
		 * key — an approval note that was silently replaced would be the unapproved-entry
		 * disclosure going missing to make room for this one.
		 *
		 * @param array{attempted:bool,ok:bool,error:string,cleared:bool} $report
		 */
		private function aiWithGeocodingNote(string $note, array $report): string {
			$geocoding = $this->geocodingFailureNote($report);

			if ($geocoding === "") {

				return $note;
			}

			return $note === "" ? $geocoding : $note . " " . $geocoding;
		}

		/**
		 * Attach an approval note to a write result, when there is one to attach.
		 *
		 * Every branch of aiUpdateEntry returns a differently-shaped result and all of
		 * them owe the same sentence; folding it in here keeps "which branch forgot"
		 * from being a thing that can happen.
		 *
		 * @param array<string,mixed> $result
		 * @return array<string,mixed>
		 */
		private function aiWithApprovalNote(array $result, string $note): array {
			if ($note !== "") {
				$result["note"] = $note;
			}

			return $result;
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

			// The column existing is not the same question as anything in the admin
			// being able to flip it back. A status column is operable by
			// a human only when the module declares the matching action — on itself or
			// on one of its views — and a hand-built module, or a scaffolded one whose
			// action was later removed in the Module Designer, can carry the column
			// without it. Status columns are not form fields either, so the entry editor
			// doesn't render them: this tool would be the only surface that can reach it.
			// Disclosed rather than refused, because the REST toggle does it too.
			$no_admin_action = $this->aiFlagWithoutActionNote($module, $flag);

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
			$preview = [
				"action" => "set_module_entry_flag",
				"module" => $name,
				"entry_id" => $entry_id,
				"entry" => $label,
				"flag" => $flag,
				"from" => $current,
				"to" => $value,
			];

			// Archiving is the same act as deleting one surface over: it hides a row that
			// other entries' relations still name, on a card that said only which flag
			// flipped. Reversible, so it is disclosed and never refused — and only when
			// the flag is going *on*, since restoring a row is what fixes this.
			if ($flag === "archived" && $value) {
				$references = $this->aiEntryReferenceUsage($table, $entry_id);

				if ($references) {
					$preview["references"] = $references;
				}
			}

			if ($no_admin_action !== null) {
				$preview["no_admin_action"] = $no_admin_action;
			}

			return [
				"ok" => true,
				"summary" => "{$verb} “{$label}” in the “{$name}” module. This takes effect live once you approve."
					. ($no_admin_action !== null ? " " . $no_admin_action : ""),
				"preview" => $preview,
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

			// And the module's action can be removed in the Module Designer inside the
			// same window, which is the whole reason the card's claim is re-asked rather
			// than carried over from staging.
			$no_admin_action = $this->aiFlagWithoutActionNote($module, $flag);

			if (PermissionService::userRowLevel($user, $module, $row) !== "p") {
				throw new AuthorizationException("Publisher access required to change entry flags");
			}

			$next = !empty($payload["value"]) ? "on" : "";
			SQL::update($table, $entry_id, [$flag => $next]);
			BigTreeAutoModule::recacheItem($entry_id, $table);
			$this->reindexAfterFlag($table, $entry_id, $flag);

			Hooks::fire("module_entry.{$flag}", [
				"module" => $module_id, "table" => $table, "id" => $entry_id, "value" => $next, "via" => "ai_assistant",
			]);

			$result = [
				"mode" => "updated",
				"module" => $module_id,
				"entry_id" => $entry_id,
				"flag" => $flag,
				"value" => $next !== "",
			];

			if ($no_admin_action !== null) {
				$result["note"] = $no_admin_action;
			}

			return $result;
		}

		/**
		 * The sentence a flag change owes when the module has no action that can undo
		 * it in the admin, or null when it has one.
		 *
		 * aiModuleStatusActions is the same map get_module and get_module_schema
		 * already disclose, read from both places a module can declare an action; the
		 * write seam must not ask a different question from the read seams. Shared by
		 * staging and approval so the card and the result cannot disagree.
		 *
		 * @param array<string,mixed> $module
		 */
		private function aiFlagWithoutActionNote(array $module, string $flag): ?string {
			$actions = ModuleService::aiModuleStatusActions($module);

			if (isset($actions[$flag])) {

				return null;
			}

			$action = array_search($flag, ModuleService::AI_STATUS_ACTION_COLUMNS, true);

			if ($action === false) {

				return null;
			}

			return "This module's views have no {$action} action, so nothing in the admin can change it back — "
				. "only this assistant can.";
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

			// What else points at the row. A draft has no live row for anything to point
			// at, so the question is only asked for a real delete.
			$references = $is_pending ? [] : $this->aiEntryReferenceUsage((string)$resolved["table"], $entry_id);

			// Named in the summary as well as on the card: the summary is what the model
			// reads back, so this is what lets the assistant answer "is it safe to
			// delete this?" instead of the user finding out afterwards.
			if ($references) {
				$summary .= " Other records will be left pointing at it: " . implode("; ", $references) . ".";
			}

			$preview = [
				"action" => "delete_module_entry",
				"module" => $name,
				"entry_id" => $entry_id,
				"entry" => $label,
				"is_draft" => $is_pending,
				"destructive" => true,
			];

			if ($references) {
				$preview["references"] = $references;
			}

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => $preview,
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
				$this->fireEntryDeleted($module_id, $table, $raw_entry_id, true, "ai_assistant");

				return ["mode" => "deleted", "module" => $module_id, "entry_id" => $raw_entry_id];
			}

			$entry_id = (int)$raw_entry_id;

			// deleteItem now owns the full teardown — the live row, its resource
			// allocations, its tag relations, its Open Graph row, and the outstanding
			// draft's change row and allocations — and recomputes the affected tags'
			// usage_count. The explicit deallocateResources calls that used to live
			// here duplicated exactly that, so they are gone.
			BigTreeAutoModule::deleteItem($table, $entry_id);
			$this->fireEntryDeleted($module_id, $table, $entry_id, false, "ai_assistant");

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

		// How far one reference count scans before it stops and says "at least".
		// Matches AI_CALLOUT_USAGE_LIMIT: a staging call is not the place to walk a
		// whole table, and the wording is honest about being a floor either way.
		private const AI_ENTRY_REFERENCE_LIMIT = 200;

		/**
		 * What else in the CMS points *at* a module entry, as card-ready sentences.
		 *
		 * Every mutating tool that destroys or hides a record already counts its blast
		 * radius — update_template counts the pages on the template, update_callout
		 * counts the pages the callout is placed on, merge_tags counts the relations it
		 * will move — except the two entry tools that do it to an entry. deleteItem
		 * tears down everything the entry *owns* (its allocations, its tags, its Open
		 * Graph row, its draft, its vector, its connecting-table rows) and there is no
		 * such teardown for the things that own a reference to it, because rewriting
		 * another entry's stored value is not teardown: it edits records the approver
		 * never saw. So they are counted and named here instead, and the human decides.
		 *
		 * Bounded and best-effort, exactly like aiCalloutPageUsage: each leg is guarded
		 * on its own so a table that can't answer costs its own sentence rather than
		 * all three, and \Exception rather than \Throwable so a real coding mistake in
		 * here still surfaces.
		 *
		 * @param int|string $entry_id A live row id; a draft has no live row for
		 *                             anything to point at, so it returns [].
		 * @return list<string>
		 */
		private function aiEntryReferenceUsage(string $table, $entry_id): array {
			$id = trim((string)$entry_id);

			if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !ctype_digit($id) || (int)$id < 1) {

				return [];
			}

			$sentences = [];

			// Leg 1: connecting-table rows belonging to *other* entries that name this
			// one. deleteItem removes the rows this entry owns and deliberately leaves
			// these, because they are another entry's relation value.
			foreach ($this->aiInboundRelationships($table) as $relationship) {
				try {
					if (!SQL::tableExists($relationship["table"])) {

						continue;
					}

					$count = (int)SQL::fetchSingle(
						"SELECT COUNT(*) FROM (SELECT `".$relationship["other-id"]."` FROM `".$relationship["table"]."` "
							. "WHERE `".$relationship["other-id"]."` = ? LIMIT " . self::AI_ENTRY_REFERENCE_LIMIT . ") counted",
						$id
					);
				} catch (\Exception $e) {

					continue;
				}

				if ($count === 0) {

					continue;
				}

				$sentences[] = "at least {$count} " . ($count === 1 ? "entry" : "entries")
					. " in “" . $relationship["module"] . "” " . ($count === 1 ? "relates" : "relate")
					. " to this one through “" . $relationship["title"] . "”";
			}

			// Leg 2: a one-to-many value is a plain array of ids in the *owning* entry's
			// own column, so nothing outside that entry can clean it up either.
			foreach ($this->aiInboundOneToMany($table) as $relationship) {
				try {
					if (!SQL::tableExists($relationship["table"])) {

						continue;
					}

					// The column holds json_encode(["12","15"]) (createItem), so the id is
					// quoted in the blob and the quotes are what keep 12 from matching 121.
					$count = (int)SQL::fetchSingle(
						"SELECT COUNT(*) FROM (SELECT id FROM `".$relationship["table"]."` "
							. "WHERE `".$relationship["column"]."` LIKE ? LIMIT " . self::AI_ENTRY_REFERENCE_LIMIT . ") counted",
						'%"' . $id . '"%'
					);
				} catch (\Exception $e) {

					continue;
				}

				if ($count === 0) {

					continue;
				}

				$sentences[] = "at least {$count} " . ($count === 1 ? "entry" : "entries")
					. " in “" . $relationship["module"] . "” " . ($count === 1 ? "lists" : "list")
					. " it in “" . $relationship["title"] . "”";
			}

			// Leg 3: BigTree's own dependency mechanism. A grouped view groups one
			// module's entries by the rows of another module's table, and only reorder()
			// has ever consulted it — so a deleted or hidden group row leaves every entry
			// under it pointing at a group that isn't there, in a view cache
			// cacheViewData will not rebuild.
			try {
				$views = BigTreeAutoModule::getDependantViews($table);
			} catch (\Exception $e) {
				$views = [];
			}

			if ($views) {
				$titles = [];

				foreach (array_slice($views, 0, 5) as $view) {
					$titles[] = "“" . (string)($view["title"] ?? $view["id"] ?? "untitled") . "”";
				}

				$sentences[] = count($views) . " grouped " . (count($views) === 1 ? "view" : "views")
					. " (" . implode(", ", $titles) . (count($views) > 5 ? ", …" : "")
					. ") " . (count($views) === 1 ? "groups" : "group")
					. " entries by rows of this table";
			}

			return $sentences;
		}

		/**
		 * Every many-to-many relationship whose *other* side is this table — the
		 * connecting tables that can hold a row naming one of its entries.
		 *
		 * The inverse of BigTreeAutoModule::getManyToManyRelationships, which answers
		 * the outbound question (what this table's own entries own). Identifiers are
		 * gated the same way for the same reason: they are interpolated into SQL.
		 *
		 * @return list<array<string,string>>
		 */
		private function aiInboundRelationships(string $table): array {
			$relationships = [];

			foreach (BigTreeJSONDB::getAll("modules") as $module) {
				foreach ((array)($module["forms"] ?? []) as $form) {
					foreach ((array)($form["fields"] ?? []) as $field) {
						if (($field["type"] ?? "") !== "many-to-many") {

							continue;
						}

						$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

						if ((string)($settings["mtm-other-table"] ?? "") !== $table) {

							continue;
						}

						$descriptor = [
							"table" => (string)($settings["mtm-connecting-table"] ?? ""),
							"other-id" => (string)($settings["mtm-other-id"] ?? ""),
							"module" => (string)($module["name"] ?? $module["id"] ?? ""),
							"title" => (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? "a relationship"),
						];

						if (!preg_match('/^[A-Za-z0-9_]+$/', $descriptor["table"])
							|| !preg_match('/^[A-Za-z0-9_]+$/', $descriptor["other-id"])) {

							continue;
						}

						$relationships[$descriptor["table"]."\0".$descriptor["other-id"]] = $descriptor;
					}
				}
			}

			return array_values($relationships);
		}

		/**
		 * Every one-to-many field anywhere in the CMS that points at this table, with
		 * the table and column its ids are stored in.
		 *
		 * @return list<array<string,string>>
		 */
		private function aiInboundOneToMany(string $table): array {
			$relationships = [];

			foreach (BigTreeJSONDB::getAll("modules") as $module) {
				foreach ((array)($module["forms"] ?? []) as $form) {
					$owning_table = (string)($form["table"] ?? "");

					if (!preg_match('/^[A-Za-z0-9_]+$/', $owning_table)) {

						continue;
					}

					foreach ((array)($form["fields"] ?? []) as $field) {
						if (($field["type"] ?? "") !== "one-to-many") {

							continue;
						}

						$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

						if ((string)($settings["table"] ?? "") !== $table) {

							continue;
						}

						$column = (string)($field["column"] ?? $field["id"] ?? "");

						if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {

							continue;
						}

						$relationships[$owning_table."\0".$column] = [
							"table" => $owning_table,
							"column" => $column,
							"module" => (string)($module["name"] ?? $module["id"] ?? ""),
							"title" => (string)($field["title"] ?? $column),
						];
					}
				}
			}

			return array_values($relationships);
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
					// should be the sequencing, not a module that "does not exist".
					//
					// Both tools, scaffold first: create_module stages a *bare record*
					// — no table, no form, no view — so approving a card it pointed at
					// would leave the entry write just as impossible, while
					// scaffold_module is the tool that produces a usable module. Both
					// stage `name` and `route`, so either matches on the same keys; the
					// store returns the most recently staged card first, which is the
					// one the user is looking at.
					"prior_change" => [
						"tools" => ["scaffold_module", "create_module"],
						"value" => $module_id,
						"keys" => ["name", "route"],
						"label" => "A module called “{$module_id}” with an entry form",
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
				// Verbatim the state a just-approved create_module leaves behind: a
				// record with no table, no form and no view. The error named no way out
				// of it, and the one in-catalog move — scaffold_module — cannot be
				// guessed from "no editable entry form". Named rather
				// than hinted with prior_change: nothing is pending here, the module is
				// real, and what it is missing has to be built.
				return ["error" => "Module \"{$module_id}\" has no database table or entry form behind it — it is a "
					. "bare module record, which is what create_module makes. Nothing can be written into it until "
					. "one is built: a developer can propose a complete module — table, add/edit form and landing "
					. "view — with scaffold_module (that builds a new module rather than filling this one in), or "
					. "finish this one by hand in Developer → Modules → Module Designer."];
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
			// The table's real column definitions. Describing only the *form* and not
			// the storage would leave the model no way to know a field it was about to
			// fill holds 255 characters — and MySQL, in non-strict mode, wouldn't tell
			// it either.
			$columns = ColumnDomain::columns((string)$resolved["table"]);

			foreach ((array)($resolved["form"]["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$type = (string)($field["type"] ?? "text");
				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$refusal = FieldTypeDomain::refusal($type);
				$settable = $refusal === "";

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
					$entry["reason"] = $refusal;
				}

				if ($settable) {
					// Email-ness and website-ness are a `text` field's `sub_type`
					// setting, not a type of their own — this CMS has no `email` or
					// `url` field type, so without this the model saw an unadorned text
					// field and had no reason to write a well-formed address.
					$sub_type = (string)($settings["sub_type"] ?? "");

					if ($sub_type !== "") {
						$entry["sub_type"] = $sub_type;
					}

					$max_length = ColumnDomain::configuredMaxLength($settings);

					if ($max_length > 0) {
						$entry["max_length"] = $max_length;
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
			// from the card it gets back.
			$unsettable_columns = ColumnDomain::unsettableRequiredColumns(
				(string)$resolved["table"],
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null
			);
			// The other half of "what does this write actually store".
			// unsettable_columns names the columns the write leaves empty; these are
			// the ones it *fills* from something other than the model's own values —
			// and until now the schema described neither the columns nor the source
			// fields they are computed from.
			$geocoding = $this->aiGeocodingState(is_array($resolved["form"] ?? null) ? $resolved["form"] : null);
			$derived_columns = $this->aiDerivedColumns(
				is_array($resolved["form"] ?? null) ? $resolved["form"] : null,
				$geocoding
			);

			return ["schema" => [
				"module_id" => (string)$module["id"],
				"module_name" => (string)($module["name"] ?? $module["id"]),
				"form_id" => (string)($resolved["form"]["id"] ?? ""),
				"table" => $resolved["table"],
				"fields" => $fields,
				"blocked_required" => $resolved["blocked_required"],
				// Whether this form carries the two features an entry write can touch
				// beyond its own columns. Both are per-form switches, and a write to
				// either is refused on a form that doesn't have it — so the model needs
				// to read them before it proposes, not learn them from a refusal. They
				// are also what scaffold_module now sets.
				"tagging" => !empty($resolved["form"]["tagging"]),
				"open_graph" => !empty($resolved["form"]["open_graph"]),
				"group_column" => $group_column,
				"group_column_note" => $group_column !== ""
					? "This module uses group-based permissions keyed on \"{$group_column}\". Every entry must "
						. "carry a value there — an entry without one is hidden from every group-scoped editor."
					: "",
				// A view can carry a PHP callback filter that decides, per row, whether an
				// entry appears in the module's landing list at all. An entry created
				// through the form that the view then filters out exists, is correct, is
				// audited — and is invisible to the person who asked for it, who will
				// reasonably report that the assistant did nothing.
				"view_filter_note" => $this->aiViewFilterNote($module),
				// The same shape of fact, one column over. The status
				// columns this module's actions gate, and — when the table really has an
				// `approved` column — what that means for anything written into it.
				"status_actions" => ModuleService::aiModuleStatusActions($module),
				"approval_note" => $this->aiApprovalNote(
					(string)$resolved["table"],
					PermissionService::isPublisher($user, PermissionService::userModuleLevel($user, (string)$module["id"])),
					true
				),
				// What a `sub_type` on a text field means. This CMS has no `email` or
				// `url` field type — the developer's statement that a field holds an
				// address is a setting on `text`, so without passing it on the model sees
				// a plain text field and has no reason to write anything address-shaped
				// into it.
				"sub_type_note" => $this->aiSubTypeNote($fields),
				"derived_columns" => $derived_columns,
				"derived_columns_note" => $derived_columns
					? "These columns are filled by the write itself, not by you — they are not in `fields` and "
						. "supplying them is refused. Each says which columns it is computed from, so include "
						. "every one of those on a create if you want the derived value to be right."
					: "",
				// The hooks this form runs on a save, by name only — never the callable,
				// which is developer code. A `pre` hook can set or change values the
				// model never sent, and this is how it can tell the user why one appeared.
				"form_hooks" => $this->aiFormHookNames(is_array($resolved["form"] ?? null) ? $resolved["form"] : null),
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

		/** The most relation candidates one lookup hands back. */
		private const AI_RELATION_OPTION_CAP = 50;

		/**
		 * The rows a relation field can be filed against: id and label, from the
		 * field's *own* target table.
		 *
		 * `one-to-many` and `many-to-many` are writable by entry id, so something in
		 * the catalogue has to produce the ids. A relation's
		 * target is `settings["table"]` / `settings["mtm-other-table"]`, which is not
		 * required to be any module's table and in practice usually isn't one
		 * (`categories`, `regions`, `staff_types`) — so list_module_entries, which
		 * takes a module id and resolves the table through a form, could never reach
		 * it. The capability worked only when the target happened to belong to a module
		 * the actor could view, and nothing maps a table back to a module for the model
		 * to find out.
		 *
		 * Gated at module `v` — the same gate get_module_schema already passes to read
		 * the field in the first place — and bounded like every other listing seam.
		 *
		 * @param object|array|null $user
		 * @return array<string,mixed>
		 */
		public function aiRelationOptions(string $module_id, string $form_id, string $column, string $query, int $limit, int $offset, $user): array {
			$resolved = $this->aiResolveModuleForm($module_id, $form_id);

			if (isset($resolved["error"]) || !empty($resolved["ambiguous_form"])) {

				return $resolved;
			}

			$module = $resolved["module"];

			if (!PermissionService::userHasModuleAccess($user, (string)$module["id"], "v")) {

				return ["denied" => "You do not have permission to view this module."];
			}

			$column = trim($column);
			$field = null;

			foreach ((array)($resolved["form"]["fields"] ?? []) as $candidate) {
				if ((string)($candidate["column"] ?? "") === $column) {
					$field = is_array($candidate) ? $candidate : null;

					break;
				}
			}

			if ($field === null) {

				return ["error" => "This form has no field called \"{$column}\". get_module_schema lists the columns "
					. "this module's entries have."];
			}

			$type = (string)($field["type"] ?? "");

			if ($type !== "one-to-many" && $type !== "many-to-many") {

				return ["error" => "\"{$column}\" is a {$type} field, not a relationship field — there is no list of "
					. "entries to choose from. get_module_schema says what it accepts."];
			}

			$limit = max(1, min(self::AI_RELATION_OPTION_CAP, $limit));
			$offset = max(0, $offset);

			// The same seam the admin's own relation picker reads through, so the rows
			// the model is offered are the rows a person would be offered. It validates
			// the field's identifiers against a real describeTable and refuses a
			// half-configured field by throwing — which is a recoverable error here,
			// not a 400 at the top of a request.
			//
			// One row past the window, so `has_more` is a fact about the table rather
			// than about the page size — the filter-before-cap treatment every other
			// listing seam has.
			try {
				$options = (new ModuleFormService())->relationOptionsFor($field, $column, [
					"q" => $query,
					"limit" => $limit + 1,
					"offset" => $offset,
				]);
			} catch (\Throwable $e) {

				return ["error" => "\"{$column}\" can't be listed: " . $e->getMessage()
					. ". A developer has to finish the field in Developer → Modules → Module Designer."];
			}

			$items = is_array($options["items"] ?? null) ? $options["items"] : [];
			$more = count($items) > $limit;

			return [
				"module" => [
					"id" => (string)$module["id"],
					"name" => (string)($module["name"] ?? $module["id"]),
				],
				"form" => (string)($resolved["form"]["id"] ?? ""),
				"column" => $column,
				"relation_type" => $type,
				"title" => (string)($field["title"] ?? $column),
				"limit" => $limit,
				"offset" => $offset,
				"has_more" => $more,
				// {id, title} pairs. The id is what create_module_entry and
				// update_module_entry take for this column.
				"options" => array_slice($items, 0, $limit),
				"note" => $more
					? "More rows match than are shown — narrow the list with `query`, or read the next page with "
						. "`offset` " . ($offset + $limit) . "."
					: "",
			];
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
		 * A note naming the fields whose `sub_type` says what kind of value they hold,
		 * when the form has any. Shaped like group_column_note: a fact the payload's
		 * keys alone don't explain, stated once rather than inferred.
		 *
		 * @param list<array<string,mixed>> $fields The assembled schema field list.
		 */
		private function aiSubTypeNote(array $fields): string {
			$named = [];

			foreach ($fields as $field) {
				$sub_type = (string)($field["sub_type"] ?? "");

				if ($sub_type !== "") {
					$named[] = "\"" . (string)($field["column"] ?? "") . "\" holds " . ($sub_type === "email"
						? "an email address"
						: ($sub_type === "website" ? "a website address" : "a {$sub_type}"));
				}
			}

			if (!$named) {

				return "";
			}

			return "This CMS has no separate email or URL field type — a text field says what it holds through "
				. "its sub_type, and the developer has said so here: " . implode(", ", $named)
				. ". Write a value in that shape.";
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
		 * The disclosure an `approved` column owes whoever is about to write into it.
		 *
		 * A module whose scaffold requested the `approve` action gets
		 * `approved CHAR(2) NOT NULL` with no default, so a new row is born `""` —
		 * unapproved — and that is the whole point of the feature: the front end reads
		 * through `BigTreeModel::getApproved()` / `BigTreeModule::getApproved()`, which
		 * select `approved = 'on'`, and the admin's own view cache files the row as
		 * status `i`. The write succeeds, the card goes green, the audit row is
		 * written, and the press release is not on the site. The user's reasonable
		 * report is "the assistant did nothing" — exactly what view_filter_note was
		 * written to prevent, one column over.
		 *
		 * Keyed on the *table* rather than on the module's declared actions, because a
		 * hand-built module with the column and no approve action is gated just the
		 * same and reads identically to the site.
		 *
		 * `set_module_entry_flag` is publisher-only per row, so an editor has no path
		 * to approval through the assistant at all — say who does rather than offering
		 * something that will be refused.
		 *
		 * @param bool $can_approve Whether this actor is a publisher on the row.
		 * @param bool $is_new Whether the row is being created (born unapproved) or edited.
		 */
		private function aiApprovalNote(string $table, bool $can_approve, bool $is_new): string {
			$columns = ColumnDomain::columns($table);

			if (!isset($columns["approved"])) {

				return "";
			}

			return "Entries in this module are approval-gated: the table has an \"approved\" column, and "
				. ($is_new
					? "a new entry is created unapproved"
					: "this entry is not approved yet")
				. ", so the front end's getApproved() reads leave it off the live site until somebody approves it. "
				. ($can_approve
					? "You can approve it with set_module_entry_flag."
					: "Approving is publisher-only per entry, so a publisher has to do it — the assistant can't "
						. "on your behalf.");
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
		 * $existing is the content the entry already carries ([] when creating), the
		 * same parameter aiRequiredUnsettableResources takes for a page: a required
		 * field that already holds a value is not a gap. It exists for the publish-time
		 * re-ask, where the question is about a draft that may have
		 * filled some of these in.
		 *
		 * @param array<string,mixed>|null $form
		 * @param array<string,mixed> $existing
		 * @return list<string>
		 */
		private function aiRequiredUnsettableFields(?array $form, array $existing = []): array {
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

				if (FieldTypeDomain::isSettable($type)) {
					continue;
				}

				// A field the assistant can't author but the write path fills anyway.
				// applyFormFieldDefaults doesn't care what type a column's field is —
				// it seeds any column whose field declares a `default` — so refusing
				// the *entire* create for a publisher over a field that is already
				// configured to fill itself would be wrong — the same parity rule as
				// above.
				if (self::aiFieldDefaultSatisfies($settings, $field)) {
					continue;
				}

				// Derived server-side by applyEntryProcessors — required or not, the
				// model is not expected to supply them and they will be populated.
				if (FieldTypeDomain::isDerived($type)) {
					continue;
				}

				$value = $existing[$column] ?? "";

				if (is_array($value) ? (bool)$value : trim((string)$value) !== "") {
					continue;
				}

				$blocked[] = (string)($field["title"] ?? $column) . " ({$column}, {$type})";
			}

			return $blocked;
		}

		/**
		 * The required-but-unauthorable fields a queued change would publish still
		 * empty, resolved from the module and table the change row names.
		 *
		 * Both create gates relax the blocked-required refusal for a
		 * draft on purpose — a draft is the "a human completes this" case — and the
		 * card discloses it at that moment. Then the draft leaves the conversation and
		 * nothing asked again: the publish seam built a diff, said "It will go live
		 * immediately once you approve", and ran no required check of any kind. The
		 * assistant is the only writer that can put such a change in the queue (the
		 * entry editor validates before submitting), and also the tool that publishes
		 * it.
		 *
		 * The module knowledge lives here rather than in PendingChangeService so the
		 * form is resolved by the same rule the write path resolves it by: by table,
		 * because a module with more than one form has one table each.
		 *
		 * @param array<string,mixed> $data The change's data, merged over the live row.
		 * @return list<string>
		 */
		public function aiChangeBlockedRequired(string $module_id, string $table, array $data): array {
			if ($module_id === "") {

				return [];
			}

			$module = BigTreeJSONDB::get("modules", $module_id) ?: BigTreeJSONDB::get("modules", $module_id, "route");

			if (!$module) {

				return [];
			}

			$forms = array_values(is_array($module["forms"] ?? null) ? $module["forms"] : []);
			$form = null;

			foreach ($forms as $candidate) {
				if ($table !== "" && (string)($candidate["table"] ?? "") === $table) {
					$form = $candidate;

					break;
				}
			}

			// A form whose `table` is empty inherits the module's, so a single-form
			// module still resolves when the row's table doesn't literally match. More
			// than one and no match is not something to guess at — each writes to its
			// own table, and reporting the wrong form's gaps is worse than reporting none.
			if ($form === null && count($forms) === 1) {
				$form = $forms[0];
			}

			return $this->aiRequiredUnsettableFields($form, $data);
		}

		/**
		 * Form fields that are real, *optional*, and unauthorable by the assistant — the
		 * disclosure half aiRequiredUnsettableFields never covered.
		 *
		 * The required ones block the entry from going live and are already
		 * disclosed; the optional ones said nothing at all, which is the quieter
		 * failure. An entry whose gallery, matrix or upload field the assistant cannot
		 * touch lands looking complete on the card and renders empty on the site, and
		 * whoever approved it had no way to know a human still has to finish it.
		 *
		 * Labelled exactly as the required list is, so a card carrying both reads
		 * consistently.
		 *
		 * @param array<string,mixed>|null $form
		 * @return list<string>
		 */
		private function aiOptionalUnsettableFields(?array $form): array {
			$optional = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");
				$type = (string)($field["type"] ?? "text");
				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				if ($column === "" || FieldTypeDomain::isSettable($type) || FieldTypeDomain::isDerived($type)) {
					continue;
				}

				$required = !empty($settings["required"])
					|| in_array("required", $this->aiValidationRules($settings), true);

				// Already disclosed, more loudly, as a blocker.
				if ($required) {
					continue;
				}

				$optional[] = (string)($field["title"] ?? $column) . " ({$column}, {$type})";
			}

			return $optional;
		}

		/**
		 * The AI-settable field schema for a module form, keyed by column — whichever
		 * of its fields FieldTypeDomain classifies as settable, which is the text-like
		 * types plus references (by resource id) and relationships
		 * (by entry id).
		 *
		 * @param array<string,mixed>|null $form
		 * @return array<string,array<string,mixed>>
		 */
		private function aiEntrySchema(?array $form): array {
			$schema = [];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)($field["column"] ?? "");
				$type = (string)($field["type"] ?? "text");

				if ($column === "" || !FieldTypeDomain::isSettable($type)) {

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
					// Where email-ness and website-ness actually live in this CMS
					// — see the same key on the schema payload.
					"sub_type" => (string)($settings["sub_type"] ?? ""),
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
					// uncapped for the assistant. Read through
					// configuredMaxLength — the stored key is `max_length`.
					"max_length" => ColumnDomain::configuredMaxLength($settings),
					// The definition itself, for the checks that need a setting this
					// summary doesn't lift out — an image reference's min_width /
					// min_height.
					"settings" => $settings,
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

			// One implementation, shared with the page-content path, so
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
		 * @param object|array|null $user The actor, for the reference and relation checks.
		 * @return array<string,mixed> ["data" => array, "mtm" => list, "mtm_columns" => list]
		 *                             or ["error" => string]
		 */
		private function aiSiftEntryData(
			array $schema,
			array $provided,
			?array $form = null,
			string $table = "",
			array $existing = [],
			$user = null
		): array {
			$data = [];
			// Many-to-many relations don't live in the entry's row: they are `__mtm__`
			// descriptors built from the field's own settings, handed to the write
			// path's $mtm argument.
			$mtm = [];
			// …and the columns those descriptors were authored under, so the write and
			// the fingerprint can drop them. See aiWithoutMtmColumns().
			$mtm_columns = [];
			$complex = $this->aiComplexEntryColumns($schema, $form);

			foreach ($provided as $column => $value) {
				$column = (string)$column;

				if (!isset($schema[$column])) {
					if (isset($complex[$column])) {

						// The reason is the field type's own, not one wall for uploads,
						// matrices and relationships alike.
						return ["error" => "Field \"{$column}\" ({$complex[$column]["label"]}) can't be set here — "
							. $complex[$column]["reason"] . " Settable fields: "
							. $this->aiDescribeSchema($schema)];
					}

					return ["error" => "This form has no field called \"{$column}\". Settable fields: "
						. $this->aiDescribeSchema($schema)];
				}

				// Relations are the one settable shape that *is* a list, so they are
				// resolved before the scalar guard below.
				if (FieldTypeDomain::isRelation((string)$schema[$column]["type"])) {
					if ((string)$schema[$column]["type"] === "many-to-many") {
						$relation = RelationDomain::resolveManyToMany($schema[$column], $value, $user);

						if (isset($relation["error"])) {

							return ["error" => (string)$relation["error"]];
						}

						$mtm[] = $relation["mtm"];
						// Also recorded under the column, because that is how the value
						// reaches the required-field gate, the proposal preview and the
						// payload the approval re-sifts. It must not reach the *write*:
						// a module form's fields are bound to real table columns, so the
						// key is a real column here — one many-to-many/process.php marks
						// `ignore` precisely so the admin never writes the id list into
						// it. aiWithoutMtmColumns() takes it back out.
						$mtm_columns[] = $column;
						$data[$column] = $relation["mtm"]["data"];

						continue;
					}

					$relation = RelationDomain::resolveOneToMany($schema[$column], $value, $user);

					if (isset($relation["error"])) {

						return ["error" => (string)$relation["error"]];
					}

					// An array in the column, which createItem/updateItem json-encode —
					// the same thing one-to-many/process.php stores.
					$data[$column] = $relation["value"];

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

				// The value as the model wrote it, before tokenization — the form the
				// truncated-read check compares against, because the read decoded too.
				$as_written = $data[$column];

				// A reference column holds a bigtree_resources id. Resolved and checked
				// here — the row exists, the actor can see its folder, it's the right
				// kind of file, it clears the field's min_width/min_height — at staging
				// and again at approval, because this sift runs on both.
				if (FieldTypeDomain::isResourceReference((string)$schema[$column]["type"])) {
					$reference = ResourceReferenceDomain::resolve(
						$schema[$column],
						(string)$schema[$column]["type"],
						$data[$column],
						$user
					);

					if (isset($reference["error"])) {

						return ["error" => (string)$reference["error"]];
					}

					$data[$column] = (string)$reference["value"];
					// The resolved id is what the column stores, so it still has to fit
					// the column — but nothing below applies to an id: it carries no
					// markup to tokenize, no option domain and no rule string, and the
					// truncated-read refusal is about prose that came back cut.
					$as_written = $data[$column];
				} else {
					// A `link` column holds a URL and nothing else, checked before the
					// token pass through the shared implementation.
					$bad_link = FieldTypeDomain::linkShapeViolation(
						(string)($schema[$column]["title"] ?? $column),
						(string)$schema[$column]["type"],
						$data[$column]
					);

					if ($bad_link !== null) {

						return ["error" => $bad_link];
					}

					// Internal links and image sources in AI-authored markup become
					// tokens here, at the sift, so the stored form is the form the
					// approver sees on the card. Shared with the page-content path. A
					// `link` value goes through the same pass —
					// autoIPL turns a bare internal URL into the `ipl://` token the
					// field stores.
					if (in_array((string)$schema[$column]["type"], PageService::AI_LINK_BEARING_TYPES, true)) {
						$data[$column] = PageService::aiNormalizeHtmlValue($data[$column]);
					}

					$out_of_domain = $this->aiOptionViolation($schema[$column], $data[$column]);

					if ($out_of_domain !== null) {

						return ["error" => $out_of_domain];
					}

					// `required` is deliberately excluded from these rules and left to
					// the create/update gates, which know whether an empty value is this
					// edit's doing. What's left (numeric, email, link) the write path
					// would refuse outright, so catching it here turns a dead end into a
					// correction.
					$invalid = $this->aiRuleViolation($schema[$column], $data[$column]);

					if ($invalid !== null) {

						return ["error" => $invalid];
					}
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

			return ["data" => $data, "mtm" => $mtm, "mtm_columns" => $mtm_columns];
		}

		/**
		 * A sifted data set with the many-to-many columns taken back out.
		 *
		 * A many-to-many value is carried in `$data` under the field's column so the
		 * required gate, the preview and the staged payload can see it — but the row
		 * itself must never receive it. The relation lives in a connecting table, and
		 * the entry column the form binds the field to is one the admin deliberately
		 * skips (`$field["ignore"] = true` in many-to-many/process.php). Left in, the
		 * assistant would be the only writer that json-encodes an id list into that
		 * column, and the only one whose staleness fingerprint asks the database for
		 * it.
		 *
		 * @param array<string,mixed> $data
		 * @param array<string,mixed> $sifted An aiSiftEntryData() result.
		 * @return array<string,mixed>
		 */
		private function aiWithoutMtmColumns(array $data, array $sifted): array {
			$columns = is_array($sifted["mtm_columns"] ?? null) ? $sifted["mtm_columns"] : [];

			foreach ($columns as $column) {
				unset($data[(string)$column]);
			}

			return $data;
		}

		/**
		 * The storage-boundary checks, run on a sifted value once its own field rules
		 * have passed: the field's `max_length`, characters the connection can't carry,
		 * the column's real width/type/domain, and the truncated-read refusal.
		 *
		 * Everything here is the storage boundary — the point past which MySQL's
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
			$too_long = ColumnDomain::maxLengthViolation($title, $field["max_length"] ?? 0, $value);

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

			// Shared with the page-content path.
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

			return "“{$title}” " . $reason . " \"" . $this->aiPreviewScalar($value, $field)
				. "\" would be refused when the entry is saved.";
		}

		/**
		 * The form's real columns that aren't in the settable schema, each with a
		 * "Title, type" label and the field type's own refusal — everything the
		 * assistant can see exists but cannot author. Used to tell a complex field
		 * apart from a misspelling, and to say *why* rather than "it's complex".
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed>|null $form
		 * @return array<string,array{label:string,reason:string}>
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
				$refusal = FieldTypeDomain::refusal($type);
				$complex[$column] = [
					"label" => $type !== "" ? "{$title}, {$type}" : $title,
					"reason" => $refusal !== ""
						? $refusal
						: "it needs the module's own editor in the admin.",
				];
			}

			return $complex;
		}

		/**
		 * Whether a field's own settings will fill it in, so a required field carrying
		 * one is not a gap the caller has to close.
		 *
		 * Matched to what the writers actually seed: applyFormFieldDefaults and
		 * PageService::normalizePageResources both take a scalar `default`, so an
		 * empty-string default seeds "" and leaves the field as blank as it started —
		 * that one is still missing.
		 *
		 * @param array<string,mixed> $settings A field/resource settings blob.
		 */
		public static function aiFieldDefaultSatisfies(array $settings, array $field = []): bool {
			if (FieldDefaultDomain::explicitDefaultSatisfies($settings)) {

				return true;
			}

			// The draw-time defaults (default_today / default_now / default_checked, a
			// list with no blank option) are seeded by the same create seams, so a
			// required field carrying one is filled just as surely.
			return $field !== [] && FieldDefaultDomain::implicitDefault($field) !== null;
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
				if (empty($field["required"])) {

					continue;
				}

				// A field the write path is about to fill is not missing. A few lines
				// after this gate, applyFormFieldDefaults seeds every unsupplied column
				// whose field declares a `default` — and the SPA's FormRenderer seeds a
				// human's form from the same setting *before* the form's own required
				// check runs, so a human never meets this error. Reporting it was a
				// stale view of the data rather than an invariant: the
				// row that lands is non-empty either way.
				if (self::aiFieldDefaultSatisfies(is_array($field["settings"] ?? null) ? $field["settings"] : [], $field)) {

					continue;
				}

				// A relation's value is a list, so "empty" is [] rather than "" — a
				// required relationship cleared to no rows is as missing as a blank
				// string.
				$empty = !array_key_exists($column, $data)
					|| (is_array($data[$column]) ? $data[$column] === [] : $data[$column] === "");

				if ($empty) {
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
				// A text field's sub_type is what this CMS calls an email or a website
				// address — there is no field type for either.
				$sub_type = (string)($field["sub_type"] ?? "");
				$parts[] = $column . " (" . $field["type"] . ($sub_type !== "" ? "/{$sub_type}" : "")
					. ($field["required"] ? ", required" : "") . ")";
			}

			return implode(", ", $parts);
		}

		/**
		 * Field-level preview for a proposal card: the value being set, and (on update)
		 * the value it replaces.
		 *
		 * Through PreviewValue, so a link reaches the card as a link, a reference as
		 * the file it names and a relation as the rows it relates to —
		 * on both sides of the diff, since a `from` left in its stored form beside a
		 * resolved `to` reads as a change that isn't one.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $data
		 * @param array<string,mixed> $existing
		 * @param object|array|null $user
		 * @return list<array<string,mixed>>
		 */
		private function aiPreviewEntryData(array $schema, array $data, array $existing = [], $user = null): array {
			$out = [];

			foreach ($data as $column => $value) {
				$field = is_array($schema[$column] ?? null) ? $schema[$column] : [];
				$entry = [
					"column" => $column,
					"title" => (string)($schema[$column]["title"] ?? $column),
					"to" => $this->aiPreviewScalar($value, $field, $user),
				];

				// A many-to-many's current value isn't in the row — the column the form
				// binds the field to holds something else entirely — so there is no
				// `from` to show, and showing the column's contents would caption the
				// card with a value that has nothing to do with the relation.
				// get_module_entry's `related` is where the current ids come from.
				if ($existing && (string)($schema[$column]["type"] ?? "") !== "many-to-many") {
					$entry["from"] = $this->aiPreviewScalar($existing[$column] ?? "", $field, $user);
				}

				$out[] = $entry;
			}

			return $out;
		}

		/**
		 * One previewed value, resolved for a human through PreviewValue and capped
		 * after the resolving rather than before it.
		 *
		 * @param mixed $value
		 * @param array<string,mixed> $field The AI schema entry, where the caller has one.
		 * @param object|array|null $user
		 */
		private function aiPreviewScalar($value, array $field = [], $user = null): string {

			return PreviewValue::forHuman($value, $field, $user);
		}
	}
