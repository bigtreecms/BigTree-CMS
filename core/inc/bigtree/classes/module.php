<?php
	/*
		Class: BigTree\Module
			Provides an interface for handling BigTree modules.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Module extends BaseObject {

		protected $ID;

		public $Class;
		public $DeveloperOnly;
		public $Group;
		public $GroupBasedPermissions;
		public $Icon;
		public $Name;
		public $Position;
		public $Route;

		/*
			Constructor:
				Builds a Module object referencing an existing database entry.

			Parameters:
				module - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($module) {
			// Passing in just an ID
			if (!is_array($module)) {
				$module = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_modules WHERE id = ?", $module);
			}

			// Bad data set
			if (!is_array($module)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $module["id"];

				$this->Class = $module["class"];
				$this->DeveloperOnly = $module["developer_only"];
				$this->Group = $module["group"] ?: false;
				$this->GroupBasedPermissions = array_filter((array) @json_decode($module["gbp"],true));
				$this->Icon = $module["icon"];
				$this->Name = $module["name"];
				$this->Position = $module["position"];
				$this->Route = $module["route"];
			}
		}

		/*
			Function: getNavigation
				Returns an array of module actions that are in navigation.

			Returns:
				An array of ModuleAction objects.
		*/

		function getNavigation() {
			$actions = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_module_actions WHERE module = ? AND in_nav = 'on' 
												  ORDER BY position DESC, id ASC", $this->ID);
			foreach ($actions as &$action) {
				$action = new ModuleAction($action);
			}

			return $actions;
		}

		/*
			Function: getUserAccessibleGroups
				Returns an array of all groups the logged in user has access to in this module.

			Returns:
				An array of groups if a user has limited access to a module or "true" if the user has access to all groups.
		*/

		function getUserAccessibleGroups() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserAccessibleGroups not available outside logged-in user context.");
				return false;
			}

			// Admins have "true" access, all groups
			if ($admin->Level > 0) {
				return true;
			}

			// Explicit permission to the whole module, return "true" access
			if ($admin->Permissions["module"][$this->ID] && $admin->Permissions["module"][$this->ID] != "n") {
				return true;
			}

			// Go through each group and return the allowedo nes
			$groups = array();
			if (is_array($admin->Permissions["module_gbp"][$this->ID])) {
				foreach ($admin->Permissions["module_gbp"][$this->ID] as $group => $permission) {
					if ($permission && $permission != "n") {
						$groups[] = $group;
					}
				}
			}
			return $groups;
		}

		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the module or not.

			Returns:
				true if the user can access the module, otherwise false.
		*/

		function getUserCanAccess() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserCanAccess not available outside logged-in user context.");
				return false;
			}

			// Developer only module
			if ($this->DeveloperOnly && $admin->Level < 2) {
				return false;
			}

			// Not developer-only and we're an admin? You have access
			if ($admin->Level > 0) {
				return true;
			}

			$module_id = $this->ID;

			// Explicitly set permission that isn't no
			if ($admin->Permissions["module"][$module_id] && $admin->Permissions["module"][$module_id] != "n") {
				return true;
			}

			// Check if they have access to any groups
			if (isset($admin->Permissions["module_gbp"])) {
				if (is_array($admin->Permissions["module_gbp"][$module_id])) {
					foreach ($admin->Permissions["module_gbp"][$module_id] as $permission) {
						if ($permission != "n") {
							return true;
						}
					}
				}
			}

			// No access set
			return false;
		}

		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the module
			
			Returns:
				A permission level ("p" for publisher, "e" for editor, "n" for none)
		*/

		function getUserAccessLevel() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserAccessLevel not available outside logged-in user context.");
				return "n";
			}

			// Developer only module
			if ($this->DeveloperOnly && $admin->Level < 2) {
				return "n";
			}

			// Not developer-only and we're an admin? Publisher.
			if ($admin->Level > 0) {
				return "p";
			}

			// Explicitly set permission
			return $admin->Permissions["module"][$this->ID];
		}

		/*
			Function: allByGroup
				Returns a list of modules in a given group.

			Parameters:
				group - The group ID to return modules for.
				sort - The sort order (defaults to positioned)
				return_arrays - Set to true to return arrays rather than objects.
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the bigtree_modules table.
		*/

		static function allByGroup($group,$sort = "position DESC, id ASC",$return_arrays = false,$auth = true) {
			$modules = array();

			if ($group) {
				$results = BigTree::$DB->fetchAll("SELECT * FROM bigtree_modules WHERE `group` = ? ORDER BY $sort", $group);
			} else {
				$results = BigTree::$DB->fetchAll("SELECT * FROM bigtree_modules WHERE `group` = 0 OR `group` IS NULL ORDER BY $sort");
			}

			foreach ($results as $module_array) {
				$module = new Module($module_array);

				// Check auth
				if (!$auth || $module->UserCanAccess) {
					$modules[$module["id"]] = $return_arrays ? $module_array : $module;
				}
			}

			return $modules;
		}

		/*
			Function: create
				Creates a module and its class file.

			Parameters:
				name - The name of the module.
				group - The group for the module.
				class - The module class to create.
				table - The table this module relates to.
				permissions - The group-based permissions.
				icon - The icon to use.
				route - Desired route to use (defaults to auto generating if this is left false).
				developer_only - Sets a module to be only accessible/visible to developers (defaults to false).

			Returns:
				A Module object.
		*/

		function create($name,$group,$class,$table,$permissions,$icon,$route = false,$developer_only = false) {
			// Find an available module route.
			$route = $route ? $route : BigTreeCMS::urlify($name);
			if (!ctype_alnum(str_replace("-","",$route)) || strlen($route) > 127) {
				return false;
			}

			// Go through the hard coded modules
			$existing = array();
			$d = opendir(SERVER_ROOT."core/admin/modules/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			// Go through the directories (really ajax, css, images, js)
			$d = opendir(SERVER_ROOT."core/admin/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			// Go through the hard coded pages
			$d = opendir(SERVER_ROOT."core/admin/pages/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					// Drop the .php
					$existing[] = substr($f,0,-4);
				}
			}
			// Go through already created modules
			array_merge($existing,BigTreeCMS::$DB->fetchAllSingle("SELECT route FROM bigtree_modules"));

			// Get a unique route
			$x = 2;
			$original_route = $route;
			while (in_array($route,$existing)) {
				$route = $original_route."-".$x;
				$x++;
			}

			// Create class module if a class name was provided
			if ($class && !file_exists(SERVER_ROOT."custom/inc/modules/$route.php")) {
				// Class file
				BigTree::putFile(SERVER_ROOT."custom/inc/modules/$route.php",'<?php
	class '.$class.' extends BigTreeModule {
		static $RouteRegistry = array();
		var $Table = "'.$table.'";
	}
');
				// Remove cached class list.
				BigTree::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			}

			// Create it
			$id = BigTreeCMS::$DB->insert("bigtree_modules",array(
				"name" => BigTree::safeEncode($name),
				"route" => $route,
				"class" => $class,
				"icon" => $icon,
				"group" => ($group ? $group : null),
				"gbp" => $permissions,
				"developer_only" => ($developer_only ? "on" : "")
			));

			AuditTrai::track("bigtree_modules",$id,"created");

			return new Module($id);
		}

		/*
			Function: delete
				Deletes the module, all related module actions, interfaces, directories, and class files.
		*/

		function delete() {
			// Delete class file and custom directory
			BigTree::deleteFile(SERVER_ROOT."custom/inc/modules/".$this->Route.".php");
			BigTree::deleteDirectory(SERVER_ROOT."custom/admin/modules/".$this->Route."/");

			// Delete all the related auto module actions
			BigTreeCMS::$DB->delete("bigtree_module_interfaces",array("module" => $this->ID));

			// Delete actions
			BigTreeCMS::$DB->delete("bigtree_module_actions",array("module" => $this->ID));

			// Delete the module
			BigTreeCMS::$DB->delete("bigtree_modules",$this->ID);

			AuditTrail::track("bigtree_modules",$this->ID,"deleted");
		}

		/*
			Function: getCachedAccessLevel
				Returns the permission level for a given module and cached view entry.

			Parameters:
				item - (optional) The item of the module to check access for.
				table - (optional) The group based table.

			Returns:
				The permission level for the given item or module (if item was not passed).
		*/

		function getCachedAccessLevel($item = array(),$table = "") {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserCanAccess not available outside logged-in user context.");
				return "n";
			}

			$module_id = $this->ID;
			$permission = $this->UserAccessLevel;

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (empty($item) || empty($this->GroupBasedPermissions["enabled"]) || $permission == "p" || $table != $this->GroupBasedPermissions["table"]) {
				return $permission;
			}

			if (is_array($admin->Permissions["module_gbp"][$module_id])) {
				$current_gbp_value = $item["gbp_field"];
				$original_gbp_value = $item["published_gbp_field"];

				$access_level = $admin->Permissions["module_gbp"][$module_id][$current_gbp_value];
				if ($access_level != "n") {
					$original_access_level = $admin->Permissions["module_gbp"][$module_id][$original_gbp_value];
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
			Function: getEntryAccessLevel
				Returns the permission level for a given module and item.
				Can be called non-statically to check for the logged in user.

			Parameters:
				item - (optional) The item of the module to check access for.
				table - (optional) The group based table.

			Returns:
				The permission level for the given item or module (if item was not passed).

			See Also:
				<getCachedAccessLevel>
		*/

		function getEntryAccessLevel($table,$entry) {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserCanAccess not available outside logged-in user context.");
				return "n";
			}

			$module_id = $this->ID;
			$permission = $this->UserAccessLevel;

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (empty($item) || empty($this->GroupBasedPermissions["enabled"]) || $permission == "p" || $table != $this->GroupBasedPermissions["table"]) {
				return $permission;
			}

			if (is_array($admin->Permissions["module_gbp"][$module_id])) {
				$group_value = $entry[$this->GroupBasedPermissions["group_field"]];
				$group_permission = $admin->Permissions["module_gbp"][$module_id][$group_value];

				if ($group_permission != "n") {
					return $group_permission;
				}
			}

			return $permission;
		}

		/*
			Function: getGroupAccessLevel
				Returns whether or not the logged in user can access a module group.
				Utility for form field types / views -- we already know module group permissions are enabled so we skip some overhead

			Parameters:
				group - A group id.

			Returns:
				The permission level if the user can access this group, otherwise false.
		*/

		function getGroupAccessLevel($group) {
			global $admin;
			
			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Method getGroupAccessLevel not available outside logged-in user context.");
				return false;
			}

			if ($admin->Level > 0) {
				return "p";
			}

			$id = $this->ID;
			$level = false;

			// First grab the overall module access level
			if ($admin->Permissions["module"][$id] && $admin->Permissions["module"][$id] != "n") {
				$level = $admin->Permissions["module"][$id];
			}

			// See if a different level is set for an individual group
			if (is_array($admin->Permissions["module_gbp"][$id])) {
				$group_permission = $admin->Permissions["module_gbp"][$id][$group];
				if ($group_permission != "n") {
					if ($group_permission == "p" || !$level) {
						$level = $group_permission;
					}
				}
			}

			return $level;
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_modules",$this->ID,array(
				"group" => $this->Group,
				"name" => BigTree::safeEncode($this->Name),
				"route" => BigTreeCMS::$DB->unique("bigtree_modules","route",BigTreeCMS::urlify($this->Route),$this->ID),
				"class" => $this->Class,
				"icon" => $this->Icon,
				"position" => $this->Position,
				"gbp" => array_filter((array) $this->GroupBasedPermissions),
				"developer_only" => $this->DeveloperOnly ? "on" : ""
			));

			AuditTrail::track("bigtree_modules",$this->ID,"updated");

			// Clear cache file in case class or route changed
			@unlink(SERVER_ROOT."cache/bigtree-module-cache.json");

			// If this has a permissions table for group based permissions, wipe that table's view cache
			if ($this->GroupBasedPermissions["table"]) {
				BigTreeAutoModule::clearCache($this->GroupBasedPermissions["table"]);
			}
		}

		/*
			Function: update
				Updates the module properties and saves them back to the database.

			Parameters:
				name - The name of the module.
				group - The group for the module.
				class - The module class name.
				permissions - The group-based permissions.
				icon - The icon to use.
				developer_only - Sets a module to be accessible/visible to only developers.
		*/

		function update($name,$group,$class,$permissions,$icon,$developer_only = false) {
			$this->Name = $name;
			$this->Group = $group;
			$this->Class = $class;
			$this->GroupBasedPermissions = $permissions;
			$this->Icon = $icon;
			$this->DeveloperOnly = $developer_only;

			$this->save();
		}
	}
