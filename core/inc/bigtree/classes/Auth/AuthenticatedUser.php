<?php
	/*
		Class: BigTree\Auth\AuthenticatedUser
	*/
	
	namespace BigTree\Auth;
	
	use BigTree\Auth;
	use BigTree\Module;
	use BigTree\Router;
	use BigTree\SQL;
	
	class AuthenticatedUser {
		
		public $ID;
		public $Level;
		public $Permissions;
		
		/*
			Constructor:
				Sets up an authenticated user instance.

			Parameters:
				id - User ID
				level - User level (-1 = anonymous, 0 = normal user, 1 = administrator, 2 = developer)
				permissions - Array of user permissions
		*/
		
		function __construct(int $id, int $level, array $permissions) {
			$this->ID = $id;
			$this->Level = $level;
			$this->Permissions = $permissions;
		}
		
		/*
			Function: canAccess
				Returns true if user hass access to the requested object.

			Paramters:
				object - A BigTree\ModuleAction, BigTree\Module, BigTree\Page, or BigTree\ResourceFolder object.

			Returns:
				Boolean
		*/
		
		function canAccess($object): bool {
			if (get_class($object) == 'BigTree\ModuleAction') {
				if ($object->Level > $this->Level) {
					return false;
				}
				
				$module = new Module($object->Module);
				
				return $this->canAccess($module);
			} else {
				return $this->getAccessLevel($object) ? true : false;
			}
		}
		
		/*
			Function: getAccessLevel
				Returns the user's access level to the requested object.

			Paramters:
				object - A BigTree\Module, BigTree\Page, or BigTree\ResourceFolder object.
				entry - A database entry (optional, only for BigTree\Module objects only).
				table - The database table for the entry (optional, for BigTree\Module objects only).

			Returns:
				The permission level of the user.
		*/
		
		function getAccessLevel($object, ?array $entry = null, ?string $table = null): ?string {
			// Developers have universal access
			if ($this->Level > 1) {
				return "p";
			}
			
			$class = get_class($object);
			
			// Module access
			if ($class == 'BigTree\Module') {
				// Developer-only modules
				if ($this->Level < 2 && $object->DeveloperOnly) {
					return null;
				}
				
				// Admins are automatically publishers
				if ($this->Level > 0) {
					return "p";
				}
				
				// Not set or empty, no access
				if (empty($this->Permissions[$object->ID]) || $this->Permissions[$object->ID] == "n") {
					$permission = null;
				} else {
					$permission = $this->Permissions[$object->ID];
				}
				
				if (empty($entry)) {
					return $permission;
				} else {
					// No table passed? Use default module table.
					$table = $table ?: $object->Table;
					
					// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
					if (empty($object->GroupBasedPermissions["enabled"]) || $permission == "p" || $table != $object->GroupBasedPermissions["table"]) {
						return $permission;
					}
					
					if (is_array($this->Permissions["module_gbp"][$object->ID])) {
						$group_value = $entry[$object->GroupBasedPermissions["group_field"]];
						$group_permission = $this->Permissions["module_gbp"][$object->ID][$group_value];
						
						if ($group_permission != "n") {
							return $group_permission;
						}
					}
				}
				
			} elseif ($class == 'BigTree\Page') {
				// Admins have access to all pages
				if ($this->Level > 0) {
					return "p";
				}
				
				// See if this page has an explicit permission set and return it if so.
				$explicit_permission = $this->Permissions["page"][$object->ID];
				
				if ($explicit_permission == "n") {
					return null;
				} elseif ($explicit_permission && $explicit_permission != "i") {
					return $explicit_permission;
				}
				
				// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
				$page_parent = $object->Parent;
				$parent_permission = $this->Permissions["page"][$page_parent];
				
				while ((!$parent_permission || $parent_permission == "i") && $page_parent) {
					$parent_id = SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page_parent);
					$parent_permission = $this->Permissions["page"][$parent_id];
				}
				
				// If no permissions are set on the page (we hit page 0 and still nothing) or permission is "n", return not allowed.
				if (!$parent_permission || $parent_permission == "i" || $parent_permission == "n") {
					return null;
				}
				
				// Return whatever we found.
				return $parent_permission;
				
			} elseif ($class = 'BigTree\ResourceFolder') {
				// Admins have access to all folders
				if ($this->Level > 0) {
					return "p";
				}
				
				if (!empty($this->Permissions["resources"][$object->ID])) {
					$permission = $this->Permissions["resources"][$object->ID];
				} else {
					$permission = null;
				}
				
				// Loop up parents looking for an explicit permission
				$parent = $object->Parent;
				
				while ((!$permission || $permission == "i") && $parent) {
					if (!empty($this->Permissions["resources"][$parent])) {
						$permission = $this->Permissions["resources"][$parent];
					} else {
						$permission = null;
					}
					
					if (!$permission || $permission == "i") {
						$parent = SQL::fetchSingle("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $parent);
					}
				}
				
				// If we still don't have an explicit yes/no permission at root, we let them have usage permissions
				if (!$permission || $permission == "i") {
					return "e";
				}
				
				if ($permission == "n") {
					return null;
				}
				
				return $permission;
			}
			
			return null;
		}
		
		/*
			Function: getAccessibleModuleGroups
				Returns an array of all groups the user has access to in the provided module.

			Parameters:
				module - A BigTree\Module object.

			Returns:
				An array of groups if the user has limited access to a module or "true" if the user has access to all groups.
		*/
		
		function getAccessibleModuleGroups(Module $module): ?array {
			$access = $this->getAccessLevel($module);
			
			if ($access == "p") {
				return null;
			}
			
			// Go through each group and return the allowedo nes
			$groups = [];
			
			if (is_array($this->Permissions["module_gbp"][$module->ID])) {
				foreach ($this->Permissions["module_gbp"][$module->ID] as $group => $permission) {
					if ($permission && $permission != "n") {
						$groups[] = $group;
					}
				}
			}
			
			return $groups;
		}
		
		/*
			Function: getCachedAccessLevel
				Returns the permission level for a given module and cached view entry.

			Parameters:
				module - A BigTree\Module object.
				item - (optional) The item of the module to check access for.
				table - (optional) The group based table.

			Returns:
				The permission level for the given item or module (if item was not passed).
		*/
		
		function getCachedAccessLevel(Module $module, ?array $item = null, ?string $table = null): ?string {
			$module_id = $module->ID;
			$permission = $this->getAccessLevel($module);
			
			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (empty($item) ||
				empty($module->GroupBasedPermissions["enabled"]) ||
				$permission == "p" ||
				$table != $module->GroupBasedPermissions["table"]
			) {
				return $permission;
			}
			
			if (is_array($this->Permissions["module_gbp"][$module_id])) {
				$current_gbp_value = $item["gbp_field"];
				$original_gbp_value = $item["published_gbp_field"];
				$access_level = $this->Permissions["module_gbp"][$module_id][$current_gbp_value];
				
				if ($access_level != "n") {
					$original_access_level = $this->Permissions["module_gbp"][$module_id][$original_gbp_value];
					
					if ($original_access_level != "p") {
						$access_level = $original_access_level;
					}
				}
				
				if ($access_level != "n") {
					return $access_level;
				}
			}
			
			return $permission;
		}
		
		/*
			Function: getGroupAccessLevel
				Returns whether or not the user can access a module group.
				Utility for form field types / views -- we already know module group permissions are enabled so we skip some overhead

			Parameters:
				module - A BigTree\Module object.
				group - A group ID.

			Returns:
				The permission level if the user can access this group, otherwise false.
		*/
		
		function getGroupAccessLevel(Module $module, int $group): ?string {
			if ($this->Level > 0) {
				return "p";
			}
			
			$id = $module->ID;
			$level = null;
			
			// First grab the overall module access level
			if ($this->Permissions["module"][$id] && $this->Permissions["module"][$id] != "n") {
				$level = $this->Permissions["module"][$id];
			}
			
			// See if a different level is set for an individual group
			if (is_array($this->Permissions["module_gbp"][$id])) {
				$group_permission = $this->Permissions["module_gbp"][$id][$group];
				
				if ($group_permission != "n") {
					if ($group_permission == "p" || !$level) {
						$level = $group_permission;
					}
				}
			}
			
			return $level;
		}
		
		/*
			Function: requireAccess
				Checks the user's access to the requested object.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				object - A BigTree\Module, BigTree\Page, or BigTree\ResourceFolder object.

			Returns:
				The permission level of the user.
		*/
		
		function requireAccess($object): ?string {
			$access = $this->getAccessLevel($object);
			
			if ($access) {
				return $access;
			}
			
			define("BIGTREE_ACCESS_DENIED", true);
			Auth::stop(file_get_contents(Router::getIncludePath("admin/pages/_denied.php")));
			
			return null;
		}
		
		/*
			Function: requireLevel
				Requires the user to have a certain user level to continue.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				level - A user level (0 being normal user, 1 being administrator, 2 being developer)
				error_path - Path (relative to SERVER_ROOT) of the error page to serve.
		*/
		
		function requireLevel($level, $error_path = "admin/pages/_denied.php"): void {
			if (empty($this->Level) || $this->Level < $level) {
				define("BIGTREE_ACCESS_DENIED", true);
				Auth::stop(file_get_contents(Router::getIncludePath($error_path)));
			}
		}
		
		/*
			Function: requirePublisher
				Requires the user to have publisher access to continue.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				object - A BigTree\Module, BigTree\Page, or BigTree\ResourceFolder object.
		*/
		
		function requirePublisher($object): void {
			$access = $this->getAccessLevel($object);
			
			if ($access !== "p") {
				define("BIGTREE_ACCESS_DENIED", true);
				Auth::stop(file_get_contents(Router::getIncludePath("admin/pages/_denied.php")));
			}
		}
		
	}
