<?php
	namespace BigTree\Services;

	use BigTree\Api\Json;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Permission resolution. Pure functions over a user record + module/page identity.
	 *
	 * "user" inputs accept either:
	 *  - the stdClass attached to Request->user (id, level, permissions array),
	 *  - or an associative array with the same keys (legacy callers).
	 *
	 * Permission rank: n < v < e < p (none, view, edit, publisher).
	 */
	class PermissionService {
		const LEVELS = ["n" => 0, "v" => 1, "e" => 2, "p" => 3];

		/** Request-scoped id → parent memo for the page tree, shared across userPageLevel calls. */
		private static $page_parent_cache = [];

		/** Request-scoped id → parent memo for the resource-folder tree, shared across userFolderLevel calls. */
		private static $folder_parent_cache = [];

		public static function userHasModuleAccess($user, $module_id_or_route, $min = "v") {
			$rank = self::userModuleLevel($user, $module_id_or_route);

			return self::rank($rank) >= self::rank($min);
		}

		public static function userModuleLevel($user, $module_id_or_route) {
			$level = self::level($user);

			if ($level > 0) {
				return "p";
			}

			$module_id = self::resolveModuleId($module_id_or_route);

			if (!$module_id) {
				return "n";
			}

			$permissions = self::extractPermissions($user);
			$direct = $permissions["module"][$module_id] ?? "n";

			if ($direct !== "n" && $direct !== "") {
				return $direct;
			}

			// Check group-based fallbacks — best of any group permission.
			if (!empty($permissions["module_gbp"][$module_id]) && is_array($permissions["module_gbp"][$module_id])) {
				$best = "n";

				foreach ($permissions["module_gbp"][$module_id] as $gp) {
					if (self::rank($gp) > self::rank($best)) {
						$best = $gp;
					}
				}

				return $best;
			}

			return $direct ?: "n";
		}

		/**
		 * Per-row check for group-based-permissions modules.
		 * Caller has already loaded the row. Returns the effective rank.
		 */
		public static function userRowLevel($user, array $module, array $row) {
			$level = self::level($user);

			if ($level > 0) {
				return "p";
			}

			$id = $module["id"];
			$permissions = self::extractPermissions($user);
			$direct = $permissions["module"][$id] ?? "n";

			$gbp = $module["gbp"] ?? [];

			if (empty($gbp["enabled"]) || $direct === "p") {
				return $direct ?: "n";
			}

			$group_field = $gbp["group_field"] ?? "";

			if (!$group_field || !isset($row[$group_field])) {
				return $direct ?: "n";
			}

			$group_value = $row[$group_field];
			$group_perm = $permissions["module_gbp"][$id][$group_value] ?? "n";

			if ($group_perm !== "n" && $group_perm !== "") {
				return $group_perm;
			}

			return $direct ?: "n";
		}

		public static function userHasPageAccess($user, $page_id, $min = "v") {
			$rank = self::userPageLevel($user, (int)$page_id);

			return self::rank($rank) >= self::rank($min);
		}

		public static function userPageLevel($user, $page_id) {
			$level = self::level($user);

			if ($level > 0) {
				return "p";
			}

			$permissions = self::extractPermissions($user);

			// Pending-change pages: "p123" → resolve to the change's parent.
			if (!is_numeric($page_id) && is_string($page_id) && strlen($page_id) > 1 && $page_id[0] === "p") {
				$change_id = substr($page_id, 1);
				$row = SQL::fetch("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id);

				if (!$row) {
					return "n";
				}
				$changes = Json::decode($row["changes"]);

				if (!empty($changes["parent"])) {
					return self::userPageLevel($user, (int)$changes["parent"]);
				}

				return "n";
			}

			$page_id = (int)$page_id;

			// Explicit permission.
			$explicit = $permissions["page"][$page_id] ?? null;

			if ($explicit === "n") {
				return "n";
			}

			if ($explicit && $explicit !== "i") {
				return $explicit;
			}

			// Walk up the tree until we find a non-inherit permission.
			$current = $page_id;
			$seen = [];

			while ($current > 0) {
				$parent = self::pageParent($current);

				if ($parent === null) {
					return "n";
				}

				if (isset($seen[$parent])) break; // cycle safety
				$seen[$parent] = true;
				$perm = $permissions["page"][$parent] ?? null;

				if ($perm && $perm !== "i") {
					return $perm === "n" ? "n" : $perm;
				}

				$current = $parent;
			}

			// Root inheritance.
			$root_perm = $permissions["page"][0] ?? null;

			if ($root_perm && $root_perm !== "i" && $root_perm !== "n") {
				return $root_perm;
			}

			return "n";
		}

		/**
		 * Resource folder permission: walks up the folder tree until an explicit
		 * permission ("p" publisher / "e" editor / "n" none) is found, or returns
		 * the default ("e") at root.
		 *
		 * Permission map lives at $user->permissions["resources"][$folder_id].
		 * Values: "p" (publisher; can create), "e" (editor; can use), "n" (no access), "i" (inherit).
		 */
		public static function userFolderLevel($user, $folder_id) {
			$level = self::level($user);

			if ($level > 0) {
				return "p";
			}

			$permissions = self::extractPermissions($user);
			$current = (int)$folder_id;
			$seen = [];

			while (true) {
				$perm = $permissions["resources"][$current] ?? null;

				if ($perm && $perm !== "i") {
					return $perm;
				}

				if ($current === 0) return "e"; // root default for non-admins

				if (isset($seen[$current])) return "e"; // cycle safety
				$seen[$current] = true;

				$parent = self::folderParent($current);

				if ($parent === null) {
					return "e";
				}
				$current = $parent;
			}
		}

		public static function userHasFolderAccess($user, $folder_id, $min = "e") {

			return self::rank(self::userFolderLevel($user, $folder_id)) >= self::rank($min);
		}

		public static function canModifyChildren($user, array $page) {
			$level = self::level($user);

			if ($level > 0) {
				return true;
			}

			$permissions = self::extractPermissions($user);
			$path = $page["path"] ?? "";

			if ($path === "") return true; // root-level — no children gate.

			// If any descendant page has a "n" or "i" permission we can't modify, fail.
			$descendants = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE ?", $path . "%");

			foreach ($descendants as $d_id) {
				$perm = $permissions["page"][(int)$d_id] ?? null;

				if ($perm === "n") {
					return false;
				}
			}

			return true;
		}

		// — helpers —

		/**
		 * Cached "SELECT parent FROM bigtree_pages WHERE id = ?" lookup. The page list
		 * resolves access for every sibling in a folder, and every sibling walks the
		 * same ancestor chain to the root — so without memoization the identical parent
		 * rows are re-fetched once per sibling (N×depth queries for an N-child folder).
		 * Returns the parent id, or null if the page row does not exist.
		 */
		private static function pageParent(int $id): ?int {
			if (array_key_exists($id, self::$page_parent_cache)) {
				return self::$page_parent_cache[$id];
			}

			$row = SQL::fetch("SELECT parent FROM bigtree_pages WHERE id = ?", $id);
			$parent = $row ? (int)$row["parent"] : null;
			self::$page_parent_cache[$id] = $parent;

			return $parent;
		}

		/**
		 * Cached "SELECT parent FROM bigtree_resource_folders WHERE id = ?" lookup.
		 * The file manager resolves access for every folder in a listing, and every
		 * folder walks the same ancestor chain to the root — so without memoization the
		 * identical parent rows are re-fetched once per sibling. Returns the parent id,
		 * or null if the folder row does not exist.
		 */
		private static function folderParent(int $id): ?int {
			if (array_key_exists($id, self::$folder_parent_cache)) {
				return self::$folder_parent_cache[$id];
			}

			$row = SQL::fetch("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $id);
			$parent = $row ? (int)$row["parent"] : null;
			self::$folder_parent_cache[$id] = $parent;

			return $parent;
		}

		/**
		 * The user's global admin level (0 for a normal CMS user, > 0 for
		 * developers/admins who bypass per-object permission checks). Accepts the
		 * object form set by Authenticate middleware or a legacy array.
		 */
		public static function level($user): int {
			if (is_object($user)) {
				return (int)($user->level ?? 0);
			}

			if (is_array($user)) {
				return (int)($user["level"] ?? 0);
			}

			return 0;
		}

		/**
		 * Publish-rights formula shared by the page and auto-module write paths: a
		 * caller may publish when their resolved permission on the object is "p"
		 * (publisher) or they are a global admin/developer (level > 0). $level is the
		 * already-resolved rank from userPageLevel/userModuleLevel.
		 */
		public static function isPublisher($user, string $level): bool {

			return $level === "p" || self::level($user) > 0;
		}

		/**
		 * Guard for group-based-permissions modules: throw when the user has no
		 * access to this specific row (rank "n"). Callers have already loaded the
		 * row via the module's gbp group field.
		 */
		public static function assertCanEditRow($user, array $module, array $row): void {
			if (self::userRowLevel($user, $module, $row) === "n") {
				throw new AuthorizationException("Row access denied by group permissions");
			}
		}

		private static function extractPermissions($user) {
			if (is_object($user)) {
				return Json::decode($user->permissions ?? []);
			}

			if (is_array($user)) {
				return Json::decode($user["permissions"] ?? []);
			}

			return [];
		}

		private static function resolveModuleId($id_or_route) {
			if (is_numeric($id_or_route)) {
				return (int)$id_or_route;
			}

			if (!is_string($id_or_route) || $id_or_route === "") {
				return null;
			}

			// JSONDB module ids are strings like "modules-15c3…".
			$by_id = BigTreeJSONDB::get("modules", $id_or_route);

			if ($by_id && isset($by_id["id"])) {
				return $by_id["id"];
			}

			$module = BigTreeJSONDB::get("modules", $id_or_route, "route");

			return $module["id"] ?? null;
		}

		private static function rank($p) {

			return self::LEVELS[$p] ?? 0;
		}
	}
