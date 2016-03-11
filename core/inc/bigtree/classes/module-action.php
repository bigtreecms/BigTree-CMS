<?php
	/*
		Class: BigTree\ModuleAction
			Provides an interface for handling BigTree module actions.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleAction extends BaseObject {

		static $Table = "bigtree_module_actions";

		protected $ID;

		public $Icon;
		public $InNav;
		public $Interface;
		public $Level;
		public $Module;
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
				$action = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_actions WHERE id = ?", $action);
			}

			// Bad data set
			if (!is_array($action)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $action["id"];

				$this->Icon = $action["class"];
				$this->InNav = $action["in_nav"] ? true : false;
				$this->Interface = $action["interface"] ?: false;
				$this->Level = $action["level"];
				$this->Module = $action["module"];
				$this->Name = $action["name"];
				$this->Position = $action["position"];
				$this->Route = $action["route"];
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
			$route = BigTreeCMS::$DB->unique("bigtree_module_actions","route",BigTreeCMS::urlify($route),array("module" => $module),true);

			// Create
			$id = BigTreeCMS::$DB->insert("bigtree_module_actions",array(
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

		function delete($id) {
			// If this action is the only one using the interface, delete it as well
			if ($this->Interface) {
				$interface_count = BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_module_actions WHERE interface = ?",$this->Interface);
				if ($interface_count == 1) {
					BigTreeCMS::$DB->delete("bigtree_module_interfaces",$this->Interface);
					AuditTrail::track("bigtree_module_interfaces",$this->Interface,"deleted");
				}
			}

			// Delete the action
			BigTreeCMS::$DB->delete("bigtree_module_actions",$this->ID);
			AuditTrail::track("bigtree_module_actions",$this->ID,"deleted");
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

			$action = static::$DB->fetch("SELECT * FROM bigtree_module_actions WHERE interface = ? ORDER BY route DESC", $id);

			return $action ? new ModuleAction($action) : false;
		}

	}
