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

		// $this->UserCanAccess
		function _getUserCanAccess() {
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
	}
