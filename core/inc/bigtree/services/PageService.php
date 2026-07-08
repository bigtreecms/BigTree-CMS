<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Json;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Hooks;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTree;
	use SQL;

	/**
	 * Page CRUD, tree navigation, revisions, archive/publish, reorder, search.
	 *
	 * v1 scope: core CRUD + tree + revisions + reorder + archive + search.
	 * Out of scope for v1 (handled by legacy admin for now):
	 *  - Tag and open-graph wiring on create/update — SPA can call /tags/* separately
	 *  - Template publish hooks (extension surface; needs lifecycle middleware)
	 *  - Multi-site path collision dance
	 */
	class PageService {
		public function list(Request $request) {
			$parent = $request->queryInt("parent");
			$include_archived = $request->queryBool("include_archived");

			$where = "parent = ?";
			$args = [$parent];

			if (!$include_archived) {
				$where .= " AND archived = ''";
			}

			$rows = SQL::fetchAll(...array_merge([
				"SELECT id, parent, nav_title, route, in_nav, archived, position, template, external, trunk, updated_at, publish_at, expire_at, EXISTS (SELECT 1 FROM bigtree_pages c WHERE c.parent = bigtree_pages.id) AS has_children FROM bigtree_pages WHERE " . $where . " ORDER BY position DESC, nav_title ASC",
			], $args));

			$me = $request->user;

			$items = array_filter(array_map(function ($r) use ($me) {
				$level = PermissionService::userPageLevel($me, (int)$r["id"]);

				if ($level === "n") {
					return null;
				}
				return [
					"id" => (int)$r["id"],
					"parent" => (int)$r["parent"],
					"nav_title" => html_entity_decode((string)$r["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"route" => $r["route"],
					"in_nav" => Flag::isOn($r["in_nav"]),
					"archived" => Flag::isOn($r["archived"]),
					"trunk" => Flag::isOn($r["trunk"]),
					"position" => (int)$r["position"],
					"template" => $r["template"],
					"external" => html_entity_decode((string)$r["external"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"updated_at" => $r["updated_at"],
					"publish_at" => $r["publish_at"],
					"expire_at" => $r["expire_at"],
					"scheduled" => !empty($r["publish_at"]) && $r["publish_at"] > date("Y-m-d H:i:s"),
					"access" => $level,
					"has_pending_change" => false,
					"has_children" => (bool)$r["has_children"],
				];
			}, $rows));

			// Enrich with pending change state (used by the SPA to show "Changed" status)
			$visibleIds = array_column($items, "id");

			if ($visibleIds) {
				$placeholders = implode(",", array_fill(0, count($visibleIds), "?"));
				$pendingRows = SQL::fetchAll(
					"SELECT DISTINCT item_id FROM bigtree_pending_changes 
					 WHERE `table` = 'bigtree_pages' AND item_id IN ($placeholders)",
					...$visibleIds
				);
				$hasPending = array_flip(array_map("intval", array_column($pendingRows, "item_id")));
				
				foreach ($items as &$it) {
					$it["has_pending_change"] = isset($hasPending[$it["id"]]);
				}
			}

			// Include "NEW" pending pages (drafts that only live in bigtree_pending_changes).
			// These do not yet have a row in bigtree_pages.
			$pendingNew = SQL::fetchAll(
				"SELECT * FROM bigtree_pending_changes 
				 WHERE pending_page_parent = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
				$parent
			);

			foreach ($pendingNew as $pc) {
				$changes = Json::decode($pc["changes"]);

				$isArchived = !empty($changes["archived"]);
				if (!$include_archived && $isArchived) {
					continue;
				}

				$inNav = !empty($changes["in_nav"]);

				// Permission for a brand-new pending page is based on the parent
				$level = PermissionService::userPageLevel($me, $parent);
				if ($level === "n") {
					continue;
				}

				$publishAt = $changes["publish_at"] ?? null;
				$isScheduled = $publishAt && $publishAt > date("Y-m-d H:i:s");

				$items[] = [
					"id" => (int)$pc["id"],
					"parent" => $parent,
					"nav_title" => html_entity_decode((string)($changes["nav_title"] ?? ""), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"route" => (string)($changes["route"] ?? ""),
					"in_nav" => $inNav,
					"archived" => $isArchived,
					"trunk" => !empty($changes["trunk"]),
					"position" => 999999, // New pending pages sort at the end for now
					"template" => (string)($changes["template"] ?? ""),
					"external" => html_entity_decode((string)($changes["external"] ?? ""), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"updated_at" => $pc["date"],
					"publish_at" => $publishAt,
					"scheduled" => $isScheduled,
					"access" => $level,
					"has_children" => false,
					"pending" => true,
					"pending_change_id" => (int)$pc["id"],
				];
			}

			return Response::ok(array_values($items));
		}

		public function get(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$out = $this->present($page, true);

			// Surface the caller's access level so the SPA can decide whether to
			// offer "Save & Publish" (publishers only) alongside "Save".
			$out["access"] = PermissionService::userPageLevel($request->user, $id);

			// The edit screen requests pending=true so it can keep editing the queued
			// draft (mirrors legacy getPendingPage) rather than the live content.
			if (!empty($request->query["pending"])) {
				$this->applyPendingOverlay($out, $id);
			}

			if (!empty($request->query["fields"]) && strpos((string)$request->query["fields"], "lineage") !== false) {
				$out["lineage"] = $this->lineage($id);
			}

			return Response::ok($out);
		}

		// GET /pages/{id}/seo-rating — score the page's live content with the same
		// algorithm as the legacy admin (BigTreeAdmin::getPageSEORating: title, meta
		// description, H1, content length/links/readability, freshness). Returns the
		// 0-100 score, the human recommendations, and the legacy gradient color so
		// the SPA can render the rating verbatim.
		public function seoRating(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$content = Json::decode($page["resources"]);
			$seo = BigTreeAdmin::getPageSEORating($page, $content);

			// getPageSEORating returns null when the template can't be resolved (e.g.
			// an external link or a removed template) — there's nothing to rate.
			if (is_null($seo)) {
				return Response::ok([
					"available" => false,
					"score" => null,
					"recommendations" => [],
					"color" => null,
				]);
			}

			return Response::ok([
				"available" => true,
				"score" => (int)$seo["score"],
				"recommendations" => array_values($seo["recommendations"]),
				"color" => $seo["color"],
			]);
		}

		public function create(Request $request) {
			$d = $request->body;
			$parent = (int)($d["parent"] ?? 0);
			$this->enforce($request->user, $parent, "e", "create child page");

			// Publishers (and global admins/devs) may write live by passing publish=true;
			// everyone else — and publishers who leave it off — creates a NEW pending draft.
			$access = PermissionService::userPageLevel($request->user, $parent);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (empty($d["publish"]) || !$can_publish) {
				$pending_id = $this->writePendingPageChange($request->user, "NEW", $parent, $d);

				Hooks::fire("page.pending_created", [
					"parent" => $parent, "pending_change_id" => (int)$pending_id,
				], ["user_id" => $request->user->id]);

				return Response::created(["pending_change_id" => (int)$pending_id, "pending" => true], null);
			}

			return Response::created($this->performCreate($d, $request->user), null);
		}

		// Live page insert, shared by create() (publish path) and the pending-change
		// publish flow in publishPendingChange(). Returns the presented page payload.
		private function performCreate(array $d, $user): array {
			$parent = (int)($d["parent"] ?? 0);
			$nav_title = trim((string)$d["nav_title"]);
			$title = trim((string)($d["title"] ?? $nav_title));
			$route = $d["route"] ?? BigTreeCMS::urlify($nav_title);
			$route = $this->uniqueRoute($parent, $route);
			$parent_path = $parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$path = ($parent_path ? $parent_path . "/" : "") . $route;

			// Only developers can flag a page as a site trunk. Silently strip the flag
			// for non-dev callers so the request still succeeds (matches legacy createPage).
			$trunk_requested = !empty($d["trunk"]);
			$trunk_value = Flag::checkbox($trunk_requested && (int)$user->level >= 2);

			$insert = [
				"trunk" => $trunk_value,
				"parent" => $parent,
				"in_nav" => Flag::checkbox($d["in_nav"] ?? null),
				"nav_title" => BigTree::safeEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"title" => BigTree::safeEncode($title),
				"meta_keywords" => $d["meta_keywords"] ?? "",
				"meta_description" => $d["meta_description"] ?? "",
				"seo_invisible" => Flag::checkbox($d["seo_invisible"] ?? null),
				"template" => $d["template"] ?? "",
				"external" => $d["external"] ?? "",
				"new_window" => Flag::checkbox($d["new_window"] ?? null),
				"resources" => json_encode($d["resources"] ?? new \stdClass()),
				"archived" => "",
				"archived_inherited" => "",
				"publish_at" => $d["publish_at"] ?? null,
				"expire_at" => $d["expire_at"] ?? null,
				"max_age" => (int)($d["max_age"] ?? 0),
				"last_edited_by" => $user->id,
				"position" => 0,
				"created_at" => "NOW()",
				"updated_at" => "NOW()",
			];

			$id = (int)SQL::insert("bigtree_pages", $insert);

			// If this new page takes over a previously-redirected route, the stale
			// redirect would steal traffic — drop it. (Mirrors legacy createPage:1730.)
			SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ?", $path);

			// Tags + open-graph wiring (mirrors legacy createPage).
			$this->syncTags($id, $d["tags"] ?? null);
			$this->syncOpenGraph($id, $d["open_graph"] ?? null);

			// Trunk page added → multi-site path cache becomes stale.
			if (Flag::isOn($trunk_value)) $this->invalidateMultiSiteCache();

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			$this->allocatePageResources((int)$id, $page["template"], [
				"resources" => Json::decode($page["resources"]),
				"external" => $page["external"],
			]);

			// Fire the template's publish hook (legacy convention — extensions and
			// templates can register a function that runs on every save).
			$this->fireTemplatePublishHook(
				$page["template"], (int)$id, $insert, $d["tags"] ?? [], $d["open_graph"] ?? []
			);

			Hooks::fire("page.created", $page, ["user_id" => $user->id]);

			return $this->present($page, true);
		}

		/**
		 * GET /pages/{id}/access-levels
		 * Port of legacy pages/access-levels.php: who can edit vs. publish this
		 * page. Every user is run through the page-permission resolution
		 * (explicit grant or inherited up the tree; admins+ are always
		 * publishers) and bucketed by the resulting rank.
		 */
		public function accessLevels(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_pages", $id, "Page");

			$publishers = [];
			$editors = [];
			$users = SQL::fetchAll("SELECT id, name, email, level, permissions FROM bigtree_users ORDER BY name");

			foreach ($users as $user) {
				$rank = PermissionService::userPageLevel($user, $id);

				if ($rank !== "p" && $rank !== "e") {
					continue;
				}

				$entry = [
					"id" => (int)$user["id"],
					"name" => $user["name"],
					"email" => $user["email"],
					"level" => (int)$user["level"],
				];

				if ($rank === "p") {
					$publishers[] = $entry;
				} else {
					$editors[] = $entry;
				}
			}

			return Response::ok(["publishers" => $publishers, "editors" => $editors]);
		}

		/**
		 * POST /pages/{id}/duplicate
		 * Port of legacy pages/duplicate.php: copy the page into a NEW pending
		 * draft under the same parent, with " (Copy)" titles and a fresh route.
		 * Requires publisher access on both the page and its parent, and (like
		 * legacy) refuses top-level pages. Improvement over legacy: tags and
		 * Open Graph are carried into the copy too.
		 */
		public function duplicate(Request $request) {
			$id = $request->id();
			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$parent = (int)$page["parent"];

			if ($parent < 1) {
				throw new BadRequestException("Top-level pages can't be duplicated", "not_duplicatable");
			}

			$this->enforce($request->user, $id, "p", "duplicate");
			$this->enforce($request->user, $parent, "p", "duplicate into");

			$d = [
				"parent" => $parent,
				"nav_title" => $page["nav_title"] . " (Copy)",
				"title" => $page["title"] . " (Copy)",
				"route" => "",
				"in_nav" => !empty($page["in_nav"]),
				"meta_keywords" => $page["meta_keywords"],
				"meta_description" => $page["meta_description"],
				"seo_invisible" => !empty($page["seo_invisible"]),
				"template" => $page["template"],
				"external" => $page["external"],
				"new_window" => !empty($page["new_window"]),
				"resources" => Json::decode($page["resources"]),
				"publish_at" => $page["publish_at"],
				"expire_at" => $page["expire_at"],
				"max_age" => (int)$page["max_age"],
				"tags" => array_map(function ($t) {

					return (int)$t["id"];
				}, $this->loadTags($id)),
			];

			$og = $this->loadOpenGraph($id);

			if ($og) {
				$d["open_graph"] = $og;
			}

			$pending_id = $this->writePendingPageChange($request->user, "NEW", $parent, $d);

			Hooks::fire("page.pending_created", [
				"parent" => $parent, "pending_change_id" => (int)$pending_id, "duplicated_from" => $id,
			], ["user_id" => $request->user->id]);

			return Response::created(["pending_change_id" => (int)$pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "e");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$d = $request->body;

			// Publishers (and global admins/devs) may write live by passing publish=true;
			// everyone else — and publishers who leave it off — queues an EDIT draft.
			$access = PermissionService::userPageLevel($request->user, $id);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (empty($d["publish"]) || !$can_publish) {
				$pending_id = $this->writePendingPageChange($request->user, "EDIT", $id, $d);

				Hooks::fire("page.pending_updated", [
					"id" => $id, "pending_change_id" => (int)$pending_id,
				], ["user_id" => $request->user->id]);

				return Response::ok(["pending" => true, "pending_change_id" => (int)$pending_id]);
			}

			return Response::ok($this->performUpdate($id, $page, $d, $request->user));
		}

		// Live page update, shared by update() (publish path) and the pending-change
		// publish flow in publishPendingChange(). Returns the presented page payload.
		private function performUpdate(int $id, array $page, array $d, $user): array {
			$update = [];

			if (isset($d["nav_title"])) {
				$update["nav_title"] = BigTree::safeEncode($d["nav_title"]);
			}

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode($d["title"]);
			}

			if (isset($d["meta_keywords"])) {
				$update["meta_keywords"] = (string)$d["meta_keywords"];
			}

			if (isset($d["meta_description"])) {
				$update["meta_description"] = (string)$d["meta_description"];
			}

			if (isset($d["seo_invisible"])) {
				$update["seo_invisible"] = Flag::checkbox($d["seo_invisible"]);
			}

			if (isset($d["in_nav"])) {
				$update["in_nav"] = Flag::checkbox($d["in_nav"]);
			}

			if (isset($d["template"])) {
				$update["template"] = (string)$d["template"];
			}

			if (isset($d["external"])) {
				$update["external"] = (string)$d["external"];
			}

			if (isset($d["new_window"])) {
				$update["new_window"] = Flag::checkbox($d["new_window"]);
			}

			if (isset($d["resources"])) {
				$update["resources"] = json_encode($d["resources"]);
			}

			if (isset($d["publish_at"])) {
				$update["publish_at"] = $d["publish_at"];
			}

			if (isset($d["expire_at"])) {
				$update["expire_at"] = $d["expire_at"];
			}

			if (isset($d["max_age"])) {
				$update["max_age"] = (int)$d["max_age"];
			}

			// Trunk flag is developer-only; non-devs silently can't change it.
			$trunk_changed = false;
			if (array_key_exists("trunk", $d) && (int)$user->level >= 2) {
				$new_trunk = Flag::checkbox($d["trunk"]);
				if ($new_trunk !== $page["trunk"]) {
					$update["trunk"] = $new_trunk;
					$trunk_changed = true;
				}
			}

			if (isset($d["route"]) && $d["route"] !== $page["route"]) {
				$new_route = $this->uniqueRoute((int)$page["parent"], $d["route"], $id);
				$update["route"] = $new_route;
				$parent_path = $page["parent"] ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $page["parent"]) : "";
				$new_path = ($parent_path ? $parent_path . "/" : "") . $new_route;
				$update["path"] = $new_path;
				// Drop any existing redirect that points AT the new path (something else
				// used to live there) AND any old redirect from the page's prior path
				// (we'll replace it). Then insert the fresh redirect.
				SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $page["path"], $new_path);
				SQL::insert("bigtree_route_history", ["old_route" => $page["path"], "new_route" => $new_path]);
				$this->repathChildren($page["path"], $new_path);
			}

			if ($update) {
				$update["last_edited_by"] = $user->id;
				$update["updated_at"] = "NOW()";
				SQL::update("bigtree_pages", $id, $update);
			}

			// Multi-site cache must be invalidated if the trunk flag changed, OR if the
			// route changed on a page that already IS a trunk (path map is keyed by path).
			if ($trunk_changed || (isset($update["path"]) && Flag::isOn($page["trunk"]))) {
				$this->invalidateMultiSiteCache();
			}

			// Publishing live supersedes any queued draft for this page (mirrors
			// updatePage:9819) — otherwise a stale EDIT change would keep showing
			// "Changed" and could be re-approved over the freshly published content.
			// Drop the superseded draft's resource allocations before deleting it.
			foreach (SQL::fetchAllSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $id
			) as $stale_change_id) {
				\BigTreeAdmin::deallocateResources("bigtree_pages", "p".$stale_change_id);
			}

			SQL::delete("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $id]);

			// Tags + open-graph: only touch if the caller included the keys, so a partial
			// PATCH doesn't wipe existing tags/OG just because they weren't sent.
			if (array_key_exists("tags", $d)) {
				$this->syncTags($id, $d["tags"]);
			}

			if (array_key_exists("open_graph", $d)) {
				$this->syncOpenGraph($id, $d["open_graph"]);
			}

			$fresh = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			$this->allocatePageResources($id, $fresh["template"], [
				"resources" => Json::decode($fresh["resources"]),
				"external" => $fresh["external"],
			]);

			// Template publish hook fires on update too. Pass the merged update payload
			// so the hook sees only what changed (matches legacy updatePage:9813).
			$this->fireTemplatePublishHook(
				$fresh["template"], $id, $update ?: [], $d["tags"] ?? [], $d["open_graph"] ?? []
			);

			Hooks::fire("page.updated", $fresh, ["user_id" => $user->id, "previous" => $page]);

			return $this->present($fresh, true);
		}

		/**
		 * Write (or replace) a page pending change row. Editors save here always;
		 * publishers save here when they don't explicitly publish. The stored
		 * `changes` blob is the normalized edit payload (minus the publish flag,
		 * tags, and open graph, which live in their own columns) so the approval
		 * flow can replay it through performCreate()/performUpdate().
		 *
		 * For EDIT, an existing queued change for the same page is overwritten so a
		 * user's repeated saves collapse into one draft (mirrors submitPageChange).
		 */
		private function writePendingPageChange($user, string $type, int $item_or_parent, array $d): int {
			[$changes, $tags_changes, $open_graph_changes] = $this->pendingChangeFields($user, $d);

			$row = [
				"user" => (int)$user->id,
				"date" => "NOW()",
				"table" => "bigtree_pages",
				"mtm_changes" => [],
				"tags_changes" => $tags_changes,
				"open_graph_changes" => $open_graph_changes,
				"module" => "",
			];

			if ($type === "NEW") {
				$changes["parent"] = $item_or_parent;
				$row["changes"] = $changes;
				$row["title"] = "New Page Created";
				$row["type"] = "NEW";
				$row["pending_page_parent"] = $item_or_parent;

				$change_id = (int)SQL::insert("bigtree_pending_changes", $row);
				$this->allocatePageResources("p".$change_id, $changes["template"] ?? "", $changes);

				return $change_id;
			}

			// EDIT — collapse onto any existing queued change for this page.
			$row["changes"] = $changes;
			$row["title"] = "Page Change Pending";
			$row["type"] = "EDIT";
			$row["item_id"] = $item_or_parent;
			$row["pending_page_parent"] = 0;

			$existing = SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$item_or_parent
			);

			if ($existing) {
				SQL::update("bigtree_pending_changes", (int)$existing, $row);
				$this->allocatePageResources("p".(int)$existing, $changes["template"] ?? "", $changes);

				return (int)$existing;
			}

			$change_id = (int)SQL::insert("bigtree_pending_changes", $row);
			$this->allocatePageResources("p".$change_id, $changes["template"] ?? "", $changes);

			return $change_id;
		}

		/**
		 * Normalize a submitted page body into the three stored pieces of a pending
		 * change: the `changes` blob (tags/open_graph kept inside it, only when
		 * actually submitted, so publish can mirror the live array_key_exists path)
		 * and the separate NOT NULL tags/OG columns (kept for the SPA's diff view).
		 *
		 * @return array{0: array, 1: array, 2: array} [changes, tags_changes, open_graph_changes]
		 */
		private function pendingChangeFields($user, array $d): array {
			$changes = $d;
			unset($changes["publish"]);

			// Developer-only trunk flag: strip for non-devs so it can't be smuggled in.
			if ((int)$user->level < 2) {
				unset($changes["trunk"]);
			}

			$tags_changes = array_key_exists("tags", $d) ? array_values($d["tags"] ?? []) : [];
			$open_graph_changes = array_key_exists("open_graph", $d) ? ($d["open_graph"] ?? []) : [];

			return [$changes, $tags_changes, $open_graph_changes];
		}

		/**
		 * Apply a queued page pending change to the live tree, then delete the
		 * queue row. NEW promotes the draft to a real page; EDIT replays the saved
		 * field changes onto the existing page. Called by PendingChangeService on
		 * approve. Returns the presented live page payload.
		 */
		public function publishPendingChange(array $row, $user): array {
			// tags/open_graph (when submitted) live inside the changes blob, so the
			// performCreate/performUpdate paths see them exactly as a live save would.
			$d = Json::decode($row["changes"]);

			if ($row["type"] === "NEW") {
				$d["parent"] = (int)($d["parent"] ?? $row["pending_page_parent"] ?? 0);
				$result = $this->performCreate($d, $user);
			} else {
				$id = (int)$row["item_id"];
				$page = Entity::findOrFail("bigtree_pages", $id, "Page");

				$result = $this->performUpdate($id, $page, $d, $user);
			}

			SQL::delete("bigtree_pending_changes", (int)$row["id"]);
			// performCreate/performUpdate already allocated against the live page id;
			// drop the now-deleted draft's allocations so they don't dangle.
			\BigTreeAdmin::deallocateResources("bigtree_pages", "p".(int)$row["id"]);

			return $result;
		}

		/**
		 * Overlay a live page's queued EDIT draft onto its presented payload so the
		 * edit screen keeps editing the draft instead of the published content
		 * (mirrors legacy getPendingPage). Sets `changes_applied` + `pending_change_id`
		 * so the SPA can show a "you're editing an unpublished draft" indicator.
		 */
		private function applyPendingOverlay(array &$out, int $id): void {
			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$id
			);

			if (!$change) {
				return;
			}

			$changes = Json::decode($change["changes"]);

			// The change blob stores values in the same shape the SPA submits (and
			// present() emits), so a direct overlay is safe for each known field.
			$overlay_fields = [
				"nav_title", "title", "route", "in_nav", "template", "external",
				"new_window", "meta_keywords", "meta_description", "seo_invisible",
				"publish_at", "expire_at", "max_age", "trunk", "resources", "open_graph",
			];

			// Capture the published value of each overlaid field before replacing it
			// so the SPA can show a published-vs-pending comparison per field, and
			// record which fields actually differ for the "Pending" markers.
			$original = [];
			$changed_fields = [];

			foreach ($overlay_fields as $field) {
				if (array_key_exists($field, $changes)) {
					$published = $out[$field] ?? null;
					$original[$field] = $published;
					$out[$field] = $changes[$field];

					// Compare structurally for arrays/objects, stringwise for
					// scalars, so "1" vs 1 (or reordered nothing) isn't a false diff.
					$matches = (is_array($published) || is_array($changes[$field]))
						? json_encode($published) === json_encode($changes[$field])
						: (string)$published === (string)$changes[$field];

					if (!$matches) {
						$changed_fields[] = $field;
					}
				}
			}

			$owner_id = !empty($change["user"]) ? (int)$change["user"] : null;

			$out["changes_applied"] = true;
			$out["pending_change_id"] = (int)$change["id"];
			$out["pending_original"] = $original;
			$out["changed_fields"] = $changed_fields;
			// Attribute the draft so the SPA can label it ("Your draft" vs "Draft by …").
			$out["pending_owner"] = $owner_id;
			$out["pending_owner_name"] = $owner_id
				? SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $owner_id)
				: null;
			$out["updated_at"] = $change["date"];
		}

		/**
		 * GET /pages/pending/{pcid} — load a NEW page draft (one that only lives in
		 * bigtree_pending_changes) as an edit-ready page payload. Access derives from
		 * the draft's intended parent.
		 */
		public function getPending(Request $request) {
			$pcid = $request->routeParam("pcid", "int");
			$change = $this->loadPendingNew($pcid);
			$parent = (int)$change["pending_page_parent"];
			$this->enforce($request->user, $parent, "e", "edit draft");

			$out = $this->presentPending($change, $request->user);

			if (!empty($request->query["fields"]) && strpos((string)$request->query["fields"], "lineage") !== false) {
				$out["lineage"] = $this->lineage($parent);
			}

			return Response::ok($out);
		}

		/**
		 * PATCH /pages/pending/{pcid} — re-save a NEW page draft, or (with publish=true
		 * and publisher rights) promote it to a live page. Returns the live page
		 * payload on publish, or a pending marker on draft save.
		 */
		public function updatePending(Request $request) {
			$pcid = $request->routeParam("pcid", "int");
			$change = $this->loadPendingNew($pcid);
			$parent = (int)$change["pending_page_parent"];
			$this->enforce($request->user, $parent, "e", "edit draft");

			$d = $request->body;
			$access = PermissionService::userPageLevel($request->user, $parent);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (!empty($d["publish"]) && $can_publish) {
				// Replay the latest form data into the change before promoting it, so
				// publish reflects what's on screen, then publishPendingChange creates
				// the live page and drops the queue row.
				$change["changes"] = $this->stampPendingNew($pcid, $request->user, $parent, $d);
				// publishPendingChange → performCreate fires page.created itself.
				$result = $this->publishPendingChange($change, $request->user);

				return Response::ok($result);
			}

			$this->stampPendingNew($pcid, $request->user, $parent, $d);

			Hooks::fire("page.pending_updated", [
				"pending_change_id" => $pcid, "parent" => $parent,
			], ["user_id" => $request->user->id]);

			return Response::ok(["pending" => true, "pending_change_id" => $pcid]);
		}

		/** Load a NEW page draft row or 404. */
		private function loadPendingNew(int $pcid): array {
			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE id = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
				$pcid
			);

			if (!$change) {
				throw new NotFoundException("Page draft $pcid not found");
			}

			return $change;
		}

		/** Rewrite a NEW draft's stored data from a fresh submission; returns the new changes array. */
		private function stampPendingNew(int $pcid, $user, int $parent, array $d): array {
			[$changes, $tags_changes, $open_graph_changes] = $this->pendingChangeFields($user, $d);
			$changes["parent"] = $parent;

			SQL::update("bigtree_pending_changes", $pcid, [
				"user" => (int)$user->id,
				"date" => "NOW()",
				"changes" => $changes,
				"tags_changes" => $tags_changes,
				"open_graph_changes" => $open_graph_changes,
			]);

			$this->allocatePageResources("p".$pcid, $changes["template"] ?? "", $changes);

			return $changes;
		}

		/** Shape a NEW draft change row into the same payload as a live PageDetail. */
		private function presentPending(array $change, $user): array {
			$changes = Json::decode($change["changes"]);
			$parent = (int)$change["pending_page_parent"];

			return [
				"id" => 0,
				"parent" => $parent,
				"access" => PermissionService::userPageLevel($user, $parent),
				"trunk" => !empty($changes["trunk"]),
				"in_nav" => !empty($changes["in_nav"]),
				"nav_title" => (string)($changes["nav_title"] ?? ""),
				"route" => (string)($changes["route"] ?? ""),
				"path" => "",
				"title" => (string)($changes["title"] ?? ""),
				"meta_keywords" => (string)($changes["meta_keywords"] ?? ""),
				"meta_description" => (string)($changes["meta_description"] ?? ""),
				"seo_invisible" => !empty($changes["seo_invisible"]),
				"template" => (string)($changes["template"] ?? ""),
				"external" => (string)($changes["external"] ?? ""),
				"new_window" => !empty($changes["new_window"]),
				"resources" => $changes["resources"] ?? new \stdClass(),
				"archived" => !empty($changes["archived"]),
				"archived_inherited" => false,
				"publish_at" => $changes["publish_at"] ?? null,
				"expire_at" => $changes["expire_at"] ?? null,
				"max_age" => (int)($changes["max_age"] ?? 0),
				"last_edited_by" => (int)$change["user"],
				"position" => 0,
				"created_at" => $change["date"],
				"updated_at" => $change["date"],
				// A NEW draft has no live row yet, so no cached analytics.
				"ga_page_views" => null,
				"tags" => [],
				"open_graph" => $changes["open_graph"] ?? null,
				"changes_applied" => true,
				"pending" => true,
				"pending_change_id" => (int)$change["id"],
			];
		}

		public function delete(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			Entity::assertExists("bigtree_pages", $id, "Page");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);
			// Clean up rel rows; FK ON DELETE CASCADE handles open_graph + revisions etc.
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			$this->cascadeDelete($id, $page["path"]);

			// Deleting a trunk page changes the multi-site routing map.
			if (Flag::isOn($page["trunk"])) $this->invalidateMultiSiteCache();

			Hooks::fire("page.deleted", $page, ["user_id" => $request->user->id]);

			return Response::noContent();
		}

		public function archive(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "on", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "on");

			return Response::noContent();
		}

		public function unarchive(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "");

			return Response::noContent();
		}

		public function move(Request $request) {
			$id = $request->id();
			$new_parent = (int)$request->body["parent"];
			$this->enforce($request->user, $id, "p");
			$this->enforce($request->user, $new_parent, "e", "move into parent");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$parent_path = $new_parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $new_parent) : "";
			$new_path = ($parent_path ? $parent_path . "/" : "") . $page["route"];

			SQL::update("bigtree_pages", $id, ["parent" => $new_parent, "path" => $new_path, "updated_at" => "NOW()"]);
			$this->repathChildren($page["path"], $new_path);

			// Moving a trunk page changes the multi-site routing map (path-keyed).
			if (Flag::isOn($page["trunk"])) $this->invalidateMultiSiteCache();

			return Response::noContent();
		}

		/**
		 * GET /pages/sites
		 *
		 * Returns the configured multi-site map so the SPA can render a "create page
		 * under [site]" picker. In a single-site install this returns an empty array.
		 * Each entry includes the trunk page id + its current path — the SPA can use
		 * that path to display the site root, and the trunk id as the parent param
		 * when creating pages under that site.
		 */
		public function sites(Request $request) {
			global $bigtree;
			$config = $bigtree["config"]["sites"] ?? [];
			$out = [];
			foreach ($config as $key => $site) {
				$trunk_id = (int)($site["trunk"] ?? 0);
				$trunk_row = $trunk_id ? SQL::fetch("SELECT id, path, nav_title FROM bigtree_pages WHERE id = ?", $trunk_id) : null;
				$out[] = [
					"key" => $key,
					"domain" => $site["domain"] ?? "",
					"www_root" => $site["www_root"] ?? "",
					"static_root" => $site["static_root"] ?? ($site["www_root"] ?? ""),
					"trunk_id" => $trunk_id,
					"trunk_path" => $trunk_row["path"] ?? null,
					"trunk_nav_title" => $trunk_row ? html_entity_decode((string)$trunk_row["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
				];
			}
			return Response::ok($out);
		}

		/**
		 * Decide which requested ids may actually be repositioned, given the set of
		 * real child page ids of the target folder. Pending NEW pages carry a
		 * bigtree_pending_changes id (a different table), and ids from other folders
		 * are not children of $parent — both must be ignored so reorder cannot
		 * rewrite an unrelated live page's position.
		 *
		 * Pure helper (no DB) so the corruption-guard logic can be unit-tested
		 * directly. $childIds is the set of genuine child page ids; $requestedIds is
		 * the ordered id list from the request. Returns the requested ids that are
		 * real children, in the requested order.
		 *
		 * @param int[] $childIds
		 * @param int[] $requestedIds
		 * @return int[]
		 */
		public static function filterReorderableIds(array $childIds, array $requestedIds): array
		{
			$valid = array_flip(array_map("intval", $childIds));
			$reorderable = [];

			foreach ($requestedIds as $id) {
				$id = (int)$id;

				if (isset($valid[$id])) {
					$reorderable[] = $id;
				}
			}

			return $reorderable;
		}

		public function reorder(Request $request) {
			$parent = $request->routeParam("parent", "int");
			$this->enforce($request->user, $parent, "e", "reorder children of");
			$ids = $request->bodyList("ids", "int");

			// Only real pages that are actually children of $parent may be repositioned.
			// This rejects pending-change ids (which live in a different table) and ids
			// from other folders, both of which would otherwise corrupt unrelated rows.
			$childIds = [];

			if ($ids) {
				$placeholders = implode(",", array_fill(0, count($ids), "?"));
				$rows = SQL::fetchAll(
					"SELECT id FROM bigtree_pages WHERE parent = ? AND id IN ($placeholders)",
					...array_merge([$parent], $ids)
				);
				$childIds = array_map("intval", array_column($rows, "id"));
			}

			$reorderable = array_flip(self::filterReorderableIds($childIds, $ids));
			$pos = count($ids);

			foreach ($ids as $id) {
				if (isset($reorderable[$id])) {
					SQL::update("bigtree_pages", $id, ["position" => $pos--, "updated_at" => "NOW()"]);
				} else {
					// The dropped id still consumes its slot, so the surviving real
					// pages keep their intended relative spacing.
					$pos--;
				}
			}

			return Response::noContent();
		}

		public function listRevisions(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");
			$rows = SQL::fetchAll(
				"SELECT id, page, title, author, saved, saved_description, updated_at FROM bigtree_page_revisions WHERE page = ? ORDER BY updated_at DESC, id DESC",
				$id
			);

			return Response::ok(array_map(function ($r) {

				return [
					"id" => (int)$r["id"],
					"page" => (int)$r["page"],
					"title" => $r["title"],
					"author" => (int)$r["author"],
					"saved" => Flag::isOn($r["saved"]),
					"saved_description" => $r["saved_description"],
					"updated_at" => $r["updated_at"],
				];
			}, $rows));
		}

		public function saveRevision(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			$desc = $request->bodyString("description", "", false);
			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$rev_id = (int)SQL::insert("bigtree_page_revisions", [
				"page" => $id,
				"title" => $page["title"],
				"meta_description" => $page["meta_description"],
				"template" => $page["template"],
				"external" => $page["external"],
				"new_window" => $page["new_window"],
				"resources" => $page["resources"],
				"author" => $request->user->id,
				"saved" => Flag::checkbox($desc !== ""),
				"saved_description" => $desc,
				"resource_allocation" => "",
				"has_deleted_resources" => "",
			]);

			return Response::created(["id" => $rev_id], null);
		}

		public function deleteRevision(Request $request) {
			$id = $request->id();
			$rev_id = $request->routeParam("rev_id", "int");
			$this->enforce($request->user, $id, "p");
			SQL::delete("bigtree_page_revisions", $rev_id);

			return Response::noContent();
		}

		/**
		 * Restore a saved/auto revision back onto the live page. The SPA edits
		 * pages directly (there is no separate draft layer), so this overwrites
		 * the page's content columns with the revision's. The current published
		 * state is first snapshotted as an auto-revision so the restore is
		 * reversible. Publisher access required.
		 */
		public function restoreRevision(Request $request) {
			$id = $request->id();
			$rev_id = $request->routeParam("rev_id", "int");
			$this->enforce($request->user, $id, "p");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$revision = Entity::fetchOrFail(
				"SELECT * FROM bigtree_page_revisions WHERE id = ? AND page = ?",
				[$rev_id, $id],
				"Revision $rev_id not found for page $id"
			);

			// Snapshot the current published state so the restore can be undone.
			SQL::insert("bigtree_page_revisions", [
				"page" => $id,
				"title" => $page["title"],
				"meta_description" => $page["meta_description"],
				"template" => $page["template"],
				"external" => $page["external"],
				"new_window" => $page["new_window"],
				"resources" => $page["resources"],
				"author" => $request->user->id,
				"saved" => "",
				"saved_description" => "",
				"resource_allocation" => "",
				"has_deleted_resources" => "",
			]);

			$update = [
				"title" => $revision["title"],
				"meta_description" => $revision["meta_description"],
				"template" => $revision["template"],
				"external" => $revision["external"],
				"new_window" => $revision["new_window"],
				"resources" => $revision["resources"],
				"last_edited_by" => $request->user->id,
				"updated_at" => "NOW()",
			];
			SQL::update("bigtree_pages", $id, $update);

			$fresh = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			$this->allocatePageResources($id, $fresh["template"], [
				"resources" => Json::decode($fresh["resources"]),
				"external" => $fresh["external"],
			]);

			$this->fireTemplatePublishHook($fresh["template"], $id, $update, [], []);
			Hooks::fire("page.updated", $fresh, ["user_id" => $request->user->id, "previous" => $page]);

			return Response::ok($this->present($fresh, true));
		}

		public function search(Request $request) {
			$q = $request->queryString("q");

			if ($q === "") {
				return Response::ok([]);
			}
			$like = Sanitize::likeTerm($q);
			$rows = SQL::fetchAll(
				"SELECT id, nav_title, path, archived FROM bigtree_pages WHERE nav_title LIKE ? OR title LIKE ? ORDER BY archived ASC, nav_title ASC LIMIT 25",
				$like, $like
			);
			$me = $request->user;
			$items = array_filter(array_map(function ($r) use ($me) {
				$level = PermissionService::userPageLevel($me, (int)$r["id"]);

				if ($level === "n") {
					return null;
				}
				return [
					"id" => (int)$r["id"],
					"nav_title" => html_entity_decode((string)$r["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"path" => $r["path"],
					"archived" => Flag::isOn($r["archived"]),
				];
			}, $rows));

			return Response::ok(array_values($items));
		}

		// — helpers —

		private function enforce($user, $page_id, $min, $action = "access") {
			if (!PermissionService::userHasPageAccess($user, $page_id, $min)) {
				throw new AuthorizationException("Insufficient page permission to $action ($min required)");
			}
		}

		/**
		 * Find a route that doesn't collide with another sibling page. At top level
		 * (parent=0) the route additionally can't collide with one of BigTreeAdmin's
		 * reserved top-level routes (ajax, css, feeds, js, sitemap.xml, _preview,
		 * _preview-pending, etc.) or with a directory name under site/. We auto-suffix
		 * with -2, -3, ... until clear (mirrors legacy createPage:1593-1612).
		 */
		private function uniqueRoute($parent, $base, $exclude_id = 0) {
			$base = $base ?: "page";
			$route = $base;
			$x = 2;

			// Reserved-route check at top level.
			if ((int)$parent === 0) {
				$reserved = \BigTreeAdmin::$ReservedTLRoutes ?? [];
				$site_dirs = $this->reservedSiteDirectories();
				while (in_array($route, $reserved, true) || in_array($route, $site_dirs, true)) {
					$route = $base . "-" . $x++;
				}
			}

			$args = [$parent, $route];
			$sql = "SELECT id FROM bigtree_pages WHERE parent = ? AND route = ?";
			if ($exclude_id) { $sql .= " AND id != ?"; $args[] = $exclude_id; }

			while (SQL::fetchSingle(...array_merge([$sql], $args))) {
				$route = $base . "-" . $x++;
				$args[1] = $route;
			}

			return $route;
		}

		/** Mirrors the legacy createPage check against directory entries in site/. */
		private function reservedSiteDirectories() {
			static $cached = null;
			if ($cached !== null) return $cached;
			$cached = [];
			$site_dir = SERVER_ROOT . "site/";
			if (is_dir($site_dir)) {
				foreach (scandir($site_dir) ?: [] as $entry) {
					if ($entry === "." || $entry === "..") continue;
					if (is_dir($site_dir . $entry)) $cached[] = $entry;
				}
			}
			return $cached;
		}

		/**
		 * Wipe the multi-site path → site map cache. BigTreeCMS rebuilds it on next
		 * frontend boot from bigtree_pages.trunk + $bigtree["config"]["sites"], so
		 * deleting the file is enough; no manual rebuild needed.
		 */
		private function invalidateMultiSiteCache() {
			$path = SERVER_ROOT . "cache/bigtree-multi-site-cache.json";
			if (file_exists($path)) @unlink($path);
		}

		/**
		 * Fire a template's publish hook with the legacy signature:
		 *   function(string $table, int $entry_id, array $data, array $mtm, array $tags, array $open_graph)
		 *
		 * Hook is a function name string stored in the template's JSONDB entry under
		 * hooks.publish. Failures are logged and swallowed — the page write already
		 * succeeded and we don't want a buggy hook to make the API return 500 on a
		 * successful save (matches legacy behavior; legacy lets the call_user_func
		 * warning surface but doesn't abort the page write either).
		 *
		 * If the hook does want to signal an error visibly, it can throw a
		 * BigTree\Api\Exceptions\ApiException — those bubble up through ErrorHandler.
		 */
		private function fireTemplatePublishHook($template_id, $page_id, array $data, $tags, $open_graph) {
			if (!$template_id) return;

			$template = \BigTreeJSONDB::get("templates", $template_id);
			$hook = $template["hooks"]["publish"] ?? null;
			if (!$hook) return;
			if (!is_callable($hook)) {
				// Legacy stores function names as strings; if the function isn't loaded
				// (e.g. an extension that ships an autoloaded hook fn isn't installed
				// in API context yet), log so the dev can investigate, but don't fail.
				BigTree::log("Template publish hook for '$template_id' is not callable: " . print_r($hook, true));
				return;
			}

			try {
				call_user_func(
					$hook,
					"bigtree_pages",
					(int)$page_id,
					$data,
					[],
					is_array($tags) ? $tags : [],
					is_array($open_graph) ? $open_graph : []
				);
			} catch (\BigTree\Api\Exceptions\ApiException $e) {
				// Let API-aware hooks signal failure cleanly.
				throw $e;
			} catch (\Throwable $e) {
				BigTree::log("Template publish hook for '$template_id' threw: " . $e->getMessage());
			}
		}

		/**
		 * (Re)allocate the resources referenced by a page or page pending change. The
		 * API stores page data without running field processors, so we scan the stored
		 * data directly. `$entry` is the live page id or a "p"-prefixed pending change
		 * id; reference-field columns (which store bare resource ids) are resolved from
		 * the template definition. Mirrors the backfill migration's page scan.
		 */
		private function allocatePageResources($entry, $template_id, array $data): void {
			$template = $template_id ? \BigTreeJSONDB::get("templates", (string)$template_id) : null;
			$reference_keys = \BigTreeAdmin::getResourceReferenceKeys($template["resources"] ?? []);

			\BigTreeAdmin::allocateResourcesFromData("bigtree_pages", $entry, $data, $reference_keys);
		}

		private function repathChildren($old_path, $new_path) {
			$descendants = SQL::fetchAll("SELECT id, path FROM bigtree_pages WHERE path LIKE ?", $old_path . "/%");

			foreach ($descendants as $d) {
				$updated = $new_path . substr($d["path"], strlen($old_path));
				SQL::update("bigtree_pages", $d["id"], ["path" => $updated]);
			}
		}

		private function cascadeDelete($id, $path) {
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE ?", $path . "%");

			foreach ($children as $cid) {
				// Drop resource allocations for the page and any queued drafts before
				// the row goes away, so nothing dangles in bigtree_resource_allocation.
				\BigTreeAdmin::deallocateResources("bigtree_pages", (int)$cid);

				foreach (SQL::fetchAllSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", (int)$cid
				) as $change_id) {
					\BigTreeAdmin::deallocateResources("bigtree_pages", "p".$change_id);
				}

				SQL::delete("bigtree_pages", (int)$cid);
			}
		}

		private function setArchivedInherited($parent_id, $value) {
			$page = SQL::fetch("SELECT path FROM bigtree_pages WHERE id = ?", $parent_id);

			if (!$page) {
				return;
			}
			SQL::query(
				"UPDATE bigtree_pages SET archived_inherited = ?, updated_at = NOW() WHERE path LIKE ? AND id != ?",
				$value, $page["path"] . "/%", $parent_id
			);
		}

		private function lineage($page_id) {
			$out = [];
			$current = (int)$page_id;

			while ($current > 0) {
				$row = SQL::fetch("SELECT id, parent, nav_title, route FROM bigtree_pages WHERE id = ?", $current);

				if (!$row) {
					break;
				}
				array_unshift($out, [
					"id" => (int)$row["id"],
					"nav_title" => html_entity_decode((string)$row["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"route" => $row["route"],
				]);
				$current = (int)$row["parent"];
			}

			return $out;
		}

		// — Tag + Open Graph helpers —

		/**
		 * Replace the page's tag-rel rows with the given list of tag ids. Pass null
		 * (don't include the "tags" key) to leave tags untouched on PATCH.
		 */
		private function syncTags($page_id, $tag_ids) {
			if (!is_array($tag_ids)) {
				return;
			}

			$tag_ids = array_values(array_unique(array_filter(array_map("intval", $tag_ids))));

			$existing = SQL::fetchAllSingle("SELECT tag FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			$existing = array_map("intval", $existing);

			$to_add = array_diff($tag_ids, $existing);
			$to_remove = array_diff($existing, $tag_ids);

			foreach ($to_add as $tag) {
				// Confirm the tag exists before linking — silently skip orphan ids.
				if (SQL::exists("bigtree_tags", $tag)) {
					SQL::insert("bigtree_tags_rel", [
						"table" => "bigtree_pages",
						"entry" => (string)$page_id,
						"tag" => (int)$tag,
					]);
				}
			}

			if ($to_remove) {
				$placeholders = implode(",", array_fill(0, count($to_remove), "?"));
				$args = array_merge([(string)$page_id], array_values($to_remove));
				SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ? AND tag IN ($placeholders)", ...$args);
			}

			// Recompute usage counts for affected tags so the SPA sees fresh totals.
			$affected = array_values(array_unique(array_merge($to_add, $to_remove)));

			foreach ($affected as $t) {
				$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $t);
				SQL::update("bigtree_tags", $t, ["usage_count" => $count]);
			}
		}

		/**
		 * Upsert the bigtree_open_graph row for this page. Pass null (omit "open_graph"
		 * key) to leave OG untouched on PATCH; pass [] to explicitly clear.
		 */
		private function syncOpenGraph($page_id, $og) {
			if ($og === null) {
				return;
			}
			SQL::delete("bigtree_open_graph", ["table" => "bigtree_pages", "entry" => $page_id]);

			if (!is_array($og) || $og === []) {
				return;
			}

			SQL::insert("bigtree_open_graph", [
				"table" => "bigtree_pages",
				"entry" => $page_id,
				"title" => BigTree::safeEncode((string)($og["title"] ?? "")),
				"description" => BigTree::safeEncode((string)($og["description"] ?? "")),
				"type" => BigTree::safeEncode((string)($og["type"] ?? "")),
				"image" => BigTree::safeEncode((string)($og["image"] ?? "")),
				"image_width" => (int)($og["image_width"] ?? 0),
				"image_height" => (int)($og["image_height"] ?? 0),
			]);
		}

		private function loadTags($page_id) {
			$rows = SQL::fetchAll(
				"SELECT t.id, t.tag, t.route, t.usage_count
				 FROM bigtree_tags t
				 INNER JOIN bigtree_tags_rel r ON r.tag = t.id
				 WHERE r.`table` = 'bigtree_pages' AND r.entry = ?",
				$page_id
			);

			return array_map(function ($r) {

				return [
					"id" => (int)$r["id"],
					"tag" => $r["tag"],
					"route" => $r["route"],
					"usage_count" => (int)$r["usage_count"],
				];
			}, $rows);
		}

		private function loadOpenGraph($page_id) {
			$row = SQL::fetch(
				"SELECT title, description, type, image, image_width, image_height
				 FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?",
				$page_id
			);

			if (!$row) {
				return null;
			}
			return [
				"title" => $row["title"],
				"description" => $row["description"],
				"type" => $row["type"],
				"image" => $row["image"],
				"image_width" => (int)$row["image_width"],
				"image_height" => (int)$row["image_height"],
			];
		}

		private function present(array $p, $include_associations = false) {
			$out = [
				"id" => (int)$p["id"],
				"trunk" => Flag::isOn($p["trunk"]),
				"parent" => (int)$p["parent"],
				"in_nav" => Flag::isOn($p["in_nav"]),
				"nav_title" => html_entity_decode((string)$p["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"route" => $p["route"],
				"path" => $p["path"],
				"title" => html_entity_decode((string)$p["title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"meta_keywords" => html_entity_decode((string)$p["meta_keywords"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"meta_description" => html_entity_decode((string)$p["meta_description"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"seo_invisible" => Flag::isOn($p["seo_invisible"]),
				"template" => $p["template"],
				"external" => html_entity_decode((string)$p["external"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"new_window" => Flag::isOn($p["new_window"]),
				"resources" => json_decode($p["resources"] ?: "{}", true) ?: new \stdClass(),
				"archived" => Flag::isOn($p["archived"]),
				"archived_inherited" => Flag::isOn($p["archived_inherited"]),
				"publish_at" => $p["publish_at"],
				"expire_at" => $p["expire_at"],
				"max_age" => (int)$p["max_age"],
				"last_edited_by" => (int)$p["last_edited_by"],
				"position" => (int)$p["position"],
				"created_at" => $p["created_at"],
				"updated_at" => $p["updated_at"],
				// Cached last-30-days page views, populated per-path by the GA4 sync
				// (BigTreeGoogleAnalytics4::cacheInformation). NULL until first sync /
				// when analytics isn't connected.
				"ga_page_views" => isset($p["ga_page_views"]) && $p["ga_page_views"] !== null
					? (int)$p["ga_page_views"]
					: null,
			];

			if ($include_associations) {
				$out["tags"] = $this->loadTags((int)$p["id"]);
				$out["open_graph"] = $this->loadOpenGraph((int)$p["id"]);
			}

			return $out;
		}
	}
