<?php
	namespace BigTree\Services;

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

		public static function userHasModuleAccess($user, $module_id_or_route, $min = "v") {
			$rank = self::userModuleLevel($user, $module_id_or_route);
			return self::rank($rank) >= self::rank($min);
		}

		public static function userModuleLevel($user, $module_id_or_route) {
			$level = self::extractLevel($user);
			if ($level > 0) return "p";

			$module_id = self::resolveModuleId($module_id_or_route);
			if (!$module_id) return "n";

			$permissions = self::extractPermissions($user);
			$direct = $permissions["module"][$module_id] ?? "n";
			if ($direct !== "n" && $direct !== "") return $direct;

			// Check group-based fallbacks — best of any group permission.
			if (!empty($permissions["module_gbp"][$module_id]) && is_array($permissions["module_gbp"][$module_id])) {
				$best = "n";
				foreach ($permissions["module_gbp"][$module_id] as $gp) {
					if (self::rank($gp) > self::rank($best)) $best = $gp;
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
			$level = self::extractLevel($user);
			if ($level > 0) return "p";

			$id = $module["id"];
			$permissions = self::extractPermissions($user);
			$direct = $permissions["module"][$id] ?? "n";

			$gbp = $module["gbp"] ?? [];
			if (empty($gbp["enabled"]) || $direct === "p") {
				return $direct ?: "n";
			}

			$group_field = $gbp["group_field"] ?? "";
			if (!$group_field || !isset($row[$group_field])) return $direct ?: "n";

			$group_value = $row[$group_field];
			$group_perm = $permissions["module_gbp"][$id][$group_value] ?? "n";

			if ($group_perm !== "n" && $group_perm !== "") return $group_perm;

			return $direct ?: "n";
		}

		public static function userHasPageAccess($user, $page_id, $min = "v") {
			$rank = self::userPageLevel($user, (int)$page_id);
			return self::rank($rank) >= self::rank($min);
		}

		public static function userPageLevel($user, $page_id) {
			$level = self::extractLevel($user);
			if ($level > 0) return "p";

			$permissions = self::extractPermissions($user);

			// Pending-change pages: "p123" → resolve to the change's parent.
			if (!is_numeric($page_id) && is_string($page_id) && strlen($page_id) > 1 && $page_id[0] === "p") {
				$change_id = substr($page_id, 1);
				$row = SQL::fetch("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id);
				if (!$row) return "n";
				$changes = json_decode($row["changes"], true) ?: [];
				if (!empty($changes["parent"])) {
					return self::userPageLevel($user, (int)$changes["parent"]);
				}
				return "n";
			}

			$page_id = (int)$page_id;

			// Explicit permission.
			$explicit = $permissions["page"][$page_id] ?? null;
			if ($explicit === "n") return "n";
			if ($explicit && $explicit !== "i") return $explicit;

			// Walk up the tree until we find a non-inherit permission.
			$current = $page_id;
			$seen = [];
			while ($current > 0) {
				$parent_row = SQL::fetch("SELECT parent FROM bigtree_pages WHERE id = ?", $current);
				if (!$parent_row) return "n";
				$parent = (int)$parent_row["parent"];
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
			if ($root_perm && $root_perm !== "i" && $root_perm !== "n") return $root_perm;

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
			$level = self::extractLevel($user);
			if ($level > 0) return "p";

			$permissions = self::extractPermissions($user);
			$current = (int)$folder_id;
			$seen = [];

			while (true) {
				$perm = $permissions["resources"][$current] ?? null;
				if ($perm && $perm !== "i") return $perm;
				if ($current === 0) return "e"; // root default for non-admins
				if (isset($seen[$current])) return "e"; // cycle safety
				$seen[$current] = true;

				$row = SQL::fetch("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $current);
				if (!$row) return "e";
				$current = (int)$row["parent"];
			}
		}

		public static function userHasFolderAccess($user, $folder_id, $min = "e") {
			return self::rank(self::userFolderLevel($user, $folder_id)) >= self::rank($min);
		}

		public static function canModifyChildren($user, array $page) {
			$level = self::extractLevel($user);
			if ($level > 0) return true;

			$permissions = self::extractPermissions($user);
			$path = $page["path"] ?? "";
			if ($path === "") return true; // root-level — no children gate.

			// If any descendant page has a "n" or "i" permission we can't modify, fail.
			$descendants = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE ?", $path . "%");
			foreach ($descendants as $d_id) {
				$perm = $permissions["page"][(int)$d_id] ?? null;
				if ($perm === "n") return false;
			}
			return true;
		}

		// — helpers —

		private static function extractLevel($user) {
			if (is_object($user)) return (int)($user->level ?? 0);
			if (is_array($user)) return (int)($user["level"] ?? 0);
			return 0;
		}

		private static function extractPermissions($user) {
			if (is_object($user)) {
				$p = $user->permissions ?? [];
				return is_array($p) ? $p : (json_decode($p, true) ?: []);
			}
			if (is_array($user)) {
				$p = $user["permissions"] ?? [];
				if (is_string($p)) return json_decode($p, true) ?: [];
				return is_array($p) ? $p : [];
			}
			return [];
		}

		private static function resolveModuleId($id_or_route) {
			if (is_numeric($id_or_route)) return (int)$id_or_route;
			if (!is_string($id_or_route) || $id_or_route === "") return null;
			$module = BigTreeJSONDB::get("modules", $id_or_route, "route");
			return $module ? (int)$module["id"] : null;
		}

		private static function rank($p) {
			return self::LEVELS[$p] ?? 0;
		}
	}
