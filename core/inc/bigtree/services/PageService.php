<?php
	namespace BigTree\Services;

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
			$parent = (int)($request->query["parent"] ?? 0);
			$include_archived = !empty($request->query["include_archived"]);

			$where = "parent = ?";
			$args = [$parent];

			if (!$include_archived) {
				$where .= " AND archived = ''";
			}

			$rows = SQL::fetchAll(...array_merge([
				"SELECT id, parent, nav_title, route, in_nav, archived, position, template, external, trunk, updated_at, publish_at, expire_at
				 FROM bigtree_pages WHERE " . $where . " ORDER BY position DESC, nav_title ASC",
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
					"in_nav" => $r["in_nav"] === "on",
					"archived" => $r["archived"] === "on",
					"trunk" => $r["trunk"] === "on",
					"position" => (int)$r["position"],
					"template" => $r["template"],
					"external" => html_entity_decode((string)$r["external"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					"updated_at" => $r["updated_at"],
					"publish_at" => $r["publish_at"],
					"expire_at" => $r["expire_at"],
					"scheduled" => !empty($r["publish_at"]) && $r["publish_at"] > date("Y-m-d H:i:s"),
					"access" => $level,
					"has_pending_change" => false,
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
				$changes = json_decode($pc["changes"] ?: "{}", true) ?: [];

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
					"pending" => true,
					"pending_change_id" => (int)$pc["id"],
				];
			}

			return Response::ok(array_values($items));
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "v");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {
				throw new NotFoundException("Page $id not found", "resource_not_found", 404);
			}

			$out = $this->present($page, true);

			if (!empty($request->query["fields"]) && strpos((string)$request->query["fields"], "lineage") !== false) {
				$out["lineage"] = $this->lineage($id);
			}

			return Response::ok($out);
		}

		public function create(Request $request) {
			$d = $request->body;
			$parent = (int)($d["parent"] ?? 0);
			$this->enforce($request->user, $parent, "e", "create child page");

			$nav_title = trim((string)$d["nav_title"]);
			$title = trim((string)($d["title"] ?? $nav_title));
			$route = $d["route"] ?? BigTreeCMS::urlify($nav_title);
			$route = $this->uniqueRoute($parent, $route);
			$parent_path = $parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$path = ($parent_path ? $parent_path . "/" : "") . $route;

			// Only developers can flag a page as a site trunk. Silently strip the flag
			// for non-dev callers so the request still succeeds (matches legacy createPage).
			$trunk_requested = !empty($d["trunk"]);
			$trunk_value = ($trunk_requested && (int)$request->user->level >= 2) ? "on" : "";

			$insert = [
				"trunk" => $trunk_value,
				"parent" => $parent,
				"in_nav" => !empty($d["in_nav"]) ? "on" : "",
				"nav_title" => BigTree::safeEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"title" => BigTree::safeEncode($title),
				"meta_keywords" => $d["meta_keywords"] ?? "",
				"meta_description" => $d["meta_description"] ?? "",
				"seo_invisible" => !empty($d["seo_invisible"]) ? "on" : "",
				"template" => $d["template"] ?? "",
				"external" => $d["external"] ?? "",
				"new_window" => !empty($d["new_window"]) ? "on" : "",
				"resources" => json_encode($d["resources"] ?? new \stdClass()),
				"archived" => "",
				"archived_inherited" => "",
				"publish_at" => $d["publish_at"] ?? null,
				"expire_at" => $d["expire_at"] ?? null,
				"max_age" => (int)($d["max_age"] ?? 0),
				"last_edited_by" => $request->user->id,
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
			if ($trunk_value === "on") $this->invalidateMultiSiteCache();

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			// Fire the template's publish hook (legacy convention — extensions and
			// templates can register a function that runs on every save).
			$this->fireTemplatePublishHook(
				$page["template"], (int)$id, $insert, $d["tags"] ?? [], $d["open_graph"] ?? []
			);

			Hooks::fire("page.created", $page, ["user_id" => $request->user->id]);

			return Response::created($this->present($page, true), null);
		}

		public function update(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "e");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {
				throw new NotFoundException("Page $id not found", "resource_not_found", 404);
			}

			$d = $request->body;
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
				$update["seo_invisible"] = !empty($d["seo_invisible"]) ? "on" : "";
			}

			if (isset($d["in_nav"])) {
				$update["in_nav"] = !empty($d["in_nav"]) ? "on" : "";
			}

			if (isset($d["template"])) {
				$update["template"] = (string)$d["template"];
			}

			if (isset($d["external"])) {
				$update["external"] = (string)$d["external"];
			}

			if (isset($d["new_window"])) {
				$update["new_window"] = !empty($d["new_window"]) ? "on" : "";
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
			if (array_key_exists("trunk", $d) && (int)$request->user->level >= 2) {
				$new_trunk = !empty($d["trunk"]) ? "on" : "";
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
				$update["last_edited_by"] = $request->user->id;
				$update["updated_at"] = "NOW()";
				SQL::update("bigtree_pages", $id, $update);
			}

			// Multi-site cache must be invalidated if the trunk flag changed, OR if the
			// route changed on a page that already IS a trunk (path map is keyed by path).
			if ($trunk_changed || (isset($update["path"]) && $page["trunk"] === "on")) {
				$this->invalidateMultiSiteCache();
			}

			// Tags + open-graph: only touch if the caller included the keys, so a partial
			// PATCH doesn't wipe existing tags/OG just because they weren't sent.
			if (array_key_exists("tags", $d)) {
				$this->syncTags($id, $d["tags"]);
			}

			if (array_key_exists("open_graph", $d)) {
				$this->syncOpenGraph($id, $d["open_graph"]);
			}

			$fresh = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			// Template publish hook fires on update too. Pass the merged update payload
			// so the hook sees only what changed (matches legacy updatePage:9813).
			$this->fireTemplatePublishHook(
				$fresh["template"], $id, $update ?: [], $d["tags"] ?? [], $d["open_graph"] ?? []
			);

			Hooks::fire("page.updated", $fresh, ["user_id" => $request->user->id, "previous" => $page]);

			return Response::ok($this->present($fresh, true));
		}

		public function delete(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "p");

			if (!SQL::exists("bigtree_pages", $id)) {
				throw new NotFoundException("Page $id not found", "resource_not_found", 404);
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);
			// Clean up rel rows; FK ON DELETE CASCADE handles open_graph + revisions etc.
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			$this->cascadeDelete($id, $page["path"]);

			// Deleting a trunk page changes the multi-site routing map.
			if ($page["trunk"] === "on") $this->invalidateMultiSiteCache();

			Hooks::fire("page.deleted", $page, ["user_id" => $request->user->id]);

			return Response::noContent();
		}

		public function archive(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "on", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "on");

			return Response::noContent();
		}

		public function unarchive(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "");

			return Response::noContent();
		}

		public function move(Request $request) {
			$id = (int)$request->route_params["id"];
			$new_parent = (int)$request->body["parent"];
			$this->enforce($request->user, $id, "p");
			$this->enforce($request->user, $new_parent, "e", "move into parent");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {
				throw new NotFoundException("Page $id not found", "resource_not_found", 404);
			}

			$parent_path = $new_parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $new_parent) : "";
			$new_path = ($parent_path ? $parent_path . "/" : "") . $page["route"];

			SQL::update("bigtree_pages", $id, ["parent" => $new_parent, "path" => $new_path, "updated_at" => "NOW()"]);
			$this->repathChildren($page["path"], $new_path);

			// Moving a trunk page changes the multi-site routing map (path-keyed).
			if ($page["trunk"] === "on") $this->invalidateMultiSiteCache();

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

		public function reorder(Request $request) {
			$parent = (int)$request->route_params["parent"];
			$this->enforce($request->user, $parent, "e", "reorder children of");
			$ids = array_map("intval", (array)$request->body["ids"]);
			$pos = count($ids);

			foreach ($ids as $id) {
				SQL::update("bigtree_pages", $id, ["position" => $pos--, "updated_at" => "NOW()"]);
			}

			return Response::noContent();
		}

		public function listRevisions(Request $request) {
			$id = (int)$request->route_params["id"];
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
					"saved" => $r["saved"] === "on",
					"saved_description" => $r["saved_description"],
					"updated_at" => $r["updated_at"],
				];
			}, $rows));
		}

		public function saveRevision(Request $request) {
			$id = (int)$request->route_params["id"];
			$this->enforce($request->user, $id, "p");
			$desc = (string)($request->body["description"] ?? "");
			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {
				throw new NotFoundException("Page $id not found", "resource_not_found", 404);
			}

			$rev_id = (int)SQL::insert("bigtree_page_revisions", [
				"page" => $id,
				"title" => $page["title"],
				"meta_description" => $page["meta_description"],
				"template" => $page["template"],
				"external" => $page["external"],
				"new_window" => $page["new_window"],
				"resources" => $page["resources"],
				"author" => $request->user->id,
				"saved" => $desc !== "" ? "on" : "",
				"saved_description" => $desc,
				"resource_allocation" => "",
				"has_deleted_resources" => "",
			]);

			return Response::created(["id" => $rev_id], null);
		}

		public function deleteRevision(Request $request) {
			$id = (int)$request->route_params["id"];
			$rev_id = (int)$request->route_params["rev_id"];
			$this->enforce($request->user, $id, "p");
			SQL::delete("bigtree_page_revisions", $rev_id);

			return Response::noContent();
		}

		public function search(Request $request) {
			$q = trim((string)($request->query["q"] ?? ""));

			if ($q === "") {
				return Response::ok([]);
			}
			$like = "%" . str_replace("%", "\\%", $q) . "%";
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
					"archived" => $r["archived"] === "on",
				];
			}, $rows));

			return Response::ok(array_values($items));
		}

		// — helpers —

		private function enforce($user, $page_id, $min, $action = "access") {
			if (!PermissionService::userHasPageAccess($user, $page_id, $min)) {
				throw new AuthorizationException("Insufficient page permission to $action ($min required)", "permission_denied", 403);
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
				"trunk" => $p["trunk"] === "on",
				"parent" => (int)$p["parent"],
				"in_nav" => $p["in_nav"] === "on",
				"nav_title" => html_entity_decode((string)$p["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"route" => $p["route"],
				"path" => $p["path"],
				"title" => html_entity_decode((string)$p["title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"meta_keywords" => html_entity_decode((string)$p["meta_keywords"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"meta_description" => html_entity_decode((string)$p["meta_description"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"seo_invisible" => $p["seo_invisible"] === "on",
				"template" => $p["template"],
				"external" => html_entity_decode((string)$p["external"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"new_window" => $p["new_window"] === "on",
				"resources" => json_decode($p["resources"] ?: "{}", true) ?: new \stdClass(),
				"archived" => $p["archived"] === "on",
				"archived_inherited" => $p["archived_inherited"] === "on",
				"publish_at" => $p["publish_at"],
				"expire_at" => $p["expire_at"],
				"max_age" => (int)$p["max_age"],
				"last_edited_by" => (int)$p["last_edited_by"],
				"position" => (int)$p["position"],
				"created_at" => $p["created_at"],
				"updated_at" => $p["updated_at"],
			];

			if ($include_associations) {
				$out["tags"] = $this->loadTags((int)$p["id"]);
				$out["open_graph"] = $this->loadOpenGraph((int)$p["id"]);
			}

			return $out;
		}
	}
