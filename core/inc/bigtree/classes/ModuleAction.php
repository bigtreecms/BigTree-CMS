<?php
	/*
		Class: BigTree\ModuleAction
			Provides an interface for handling BigTree module actions.
	*/

	namespace BigTree;

	class ModuleAction extends BaseObject {

		static $Table = "bigtree_module_actions";

		protected $ID;
		protected $OriginalRoute;

		public $Icon;
		public $InNav;
		public $Interface;
		public $Level;
		public $ModuleID;
		public $Name;
		public $Position;
		public $Route;


		/*
			Constructor:
				Builds a ModuleAction object referencing an existing database entry.

			Parameters:
				action - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($action) {
			// Passing in just an ID
			if (!is_array($action)) {
				$action = SQL::fetch("SELECT * FROM bigtree_module_actions WHERE id = ?", $action);
			}

			// Bad data set
			if (!is_array($action)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$this->ID = $action["id"];

				$this->Icon = $action["class"];
				$this->InNav = $action["in_nav"] ? true : false;
				$this->Interface = $action["interface"] ?: false;
				$this->Level = $action["level"];
				$this->ModuleID = $action["module"];
				$this->Name = $action["name"];
				$this->Position = $action["position"];
				$this->Route = $this->OriginalRoute = $action["route"];
			}
		}

		/*
			Function: create
				Creates a module action.

			Parameters:
				module - The module to create an action for.
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.

			Returns:
				A ModuleAction object.
		*/

		static function create($module,$name,$route,$in_nav,$icon,$interface,$level = 0,$position = 0) {
			// Get a clean unique route
			$route = SQL::unique("bigtree_module_actions","route",Link::urlify($route),array("module" => $module),true);

			// Create
			$id = SQL::insert("bigtree_module_actions",array(
				"module" => $module,
				"route" => $route,
				"in_nav" => ($in_nav ? "on" : ""),
				"class" => $icon,
				"level" => intval($level),
				"interface" => ($interface ? $interface : null),
				"position" => $position
			));
			
			AuditTrail::track("bigtree_module_actions",$id,"created");

			return new ModuleAction($id);
		}

		/*
			Function: delete
				Deletes the module action and the related interface (if no other action is using it).
		*/

		function delete() {
			// If this action is the only one using the interface, delete it as well
			if ($this->Interface) {
				$interface_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_actions WHERE interface = ?",$this->Interface);
				if ($interface_count == 1) {
					SQL::delete("bigtree_module_interfaces",$this->Interface);
					AuditTrail::track("bigtree_module_interfaces",$this->Interface,"deleted");
				}
			}

			// Delete the action
			SQL::delete("bigtree_module_actions",$this->ID);
			AuditTrail::track("bigtree_module_actions",$this->ID,"deleted");
		}

		/*
			Function: exists
				Checks to see if an action exists for a given route and module.

			Parameters:
				module - The module to check.
				route - The route of the action to check.

			Returns:
				true if an action exists, otherwise false.
		*/

		static function exists($module,$route) {
			return SQL::exists("bigtree_module_actions",array("module" => $module,"route" => $route));
		}

		/*
			Function: getByInterface
				Returns the module action for a given module interface.
				Prioritizes edit action over add.
		
			Parameters:
				interface - The ID of an interface, interface array or interface object.

			Returns:
				A module action entry or false if none exists for the provided interface.
		*/

		static function getByInterface($interface) {
			if (is_object($interface)) {
				$id = $interface->ID;
			} elseif (is_array($interface)) {
				$id = $interface["id"];
			} else {
				$id = $interface;
			}

			$action = SQL::fetch("SELECT * FROM bigtree_module_actions WHERE interface = ? ORDER BY route DESC", $id);

			return $action ? new ModuleAction($action) : false;
		}

		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the action or not.

			Returns:
				true if the user can access the action, otherwise false.
		*/

		function getUserCanAccess() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				trigger_error("Property UserCanAccess not available outside logged-in user context.");
				return false;
			}

			// Check action access level
			if ($action["level"] > $admin->Level) {
				return false;
			}

			$module = new Module($this->ModuleID);
			return $module->UserCanAccess;
		}

		/*
			Function: lookup
				Returns a ModuleAction for the given module and route.

			Parameters:
				module - The module to lookup an action for.
				route - The route of the action.

			Returns:
				An array containing the action and additional commands or false if lookup failed.
		*/

		static function lookup($module,$route) {
			// For landing routes.
			if (!count($route)) {
				$route = array("");
			}

			$commands = array();

			while (count($route)) {
				$action = SQL::fetch("SELECT * FROM bigtree_module_actions 
									  WHERE module = ? AND route = ?", $module, implode("/",$route));

				// If we found an action for this sequence, return it with the extra URL route commands
				if ($action) {
					return array("action" => new ModuleAction($action), "commands" => array_reverse($commands));
				}

				// Otherwise strip off the last route as a command and try again
				$commands[] = array_pop($route);
			}

			return false;
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Make sure route is unique and clean
			$this->Route = Link::urlify($this->Route);
			if ($this->Route != $this->OriginalRoute) {
				$this->Route = SQL::unique("bigtree_module_actions","route",$this->Route,array("module" => $this->ModuleID),true);
				$this->OriginalRoute = $this->Route;
			}

			SQL::update("bigtree_module_actions",$id,array(
				"name" => Text::htmlEncode($this->Name),
				"route" => $this->Route,
				"class" => $this->Icon,
				"in_nav" => $this->InNav ? "on" : false,
				"level" => $this->Level,
				"position" => $this->Position,
				"interface" => $this->Interface ?: null
			));

			AuditTrail::track("bigtree_module_actions",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the module action.

			Parameters:
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.
		*/

		function updateModuleAction($id,$name,$route,$in_nav,$icon,$interface,$level,$position) {
			$this->Name = $name;
			$this->Route = $route;
			$this->InNav = $in_nav;
			$this->Icon = $icon;
			$this->Interface = $interface;
			$this->Level = $level;
			$this->Position = $position;

			$this->save();
		}

	}
	
