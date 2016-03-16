<?php
	/*
		Class: BigTree\ModuleInterface
			Provides an interface for handling BigTree module interfaces (views, forms, reports, etc).
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleInterface extends BaseObject {

		static $Table = "bigtree_module_interfaces";

		protected $ID;

		public $Module;
		public $Settings;
		public $Title;
		public $Type;

		/*
			Constructor:
				Builds a Extension object referencing an existing database entry.

			Parameters:
				extension - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($interface) {
			// Passing in just an ID
			if (!is_array($interface)) {
				$interface = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
			}

			// Bad data set
			if (!is_array($interface)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $interface["id"];

				$this->Module = $interface["module"];
				$this->Settings = (array) @json_decode($interface["settings"],true);
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the parent class
				$this->Title = $interface["title"];
				$this->Type = $interface["type"];
			}
		}

		/*
			Function: allByModuleAndType
				Returns an array of interfaces related to the given module.

			Parameters:
				module - The module or module ID to pull interfaces for (if false, returns all interfaces)
				type - The type of interface to return (defaults to false for all types)
				order - Sort order (defaults to title ASC)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of interfaces.
		*/

		static function allByModuleAndType($module = false,$type = false,$order = "`title` ASC") {
			$where = $parameters = array();

			// Add module restriction
			if ($module !== false) {
				$where[] = "`module` = ?";
				$parameters[] = is_array($module) ? $module["id"] : $module;
			}

			// Add type restriciton
			if ($type !== false) {
				$where[] = "`type` = ?";
				$parameters[] = $type;
			}

			// Add the query
			$where = count($where) ? " WHERE ".implode(" AND ",$where) : "";

			// Get the arrays
			$interfaces = call_user_func_array(array(BigTreeCMS::$DB,"fetchAll"),$parameters);

			// Turn into objects
			if (!$return_arrays) {
				foreach ($interfaces as &$interface) {
					$interface = new ModuleInterface($interface);
				}
			}

			return $interfaces;
		}

		/*
			Function: create
				Creates a module interface.

			Parameters:
				type - Interface type ("view", "form", "report", "embeddable-form", or an extension interface identifier)
				module - The module ID the interface is for
				title - The interface title (for admin purposes)
				table - The related table
				settings - An array of settings

			Returns:
				A ModuleInterface object.
		*/

		static function create($type,$module,$title,$table,$settings = array()) {
			$id = BigTreeCMS::$DB->insert("bigtree_module_interfaces",array(
				"type" => $type,
				"module" => intval($module),
				"title" => BigTree::safeEncode($title),
				"table" => $table,
				"settings" => $settings
			));

			AuditTrail::track("bigtree_module_interfaces",$id,"created");

			return new ModuleInterface($id);
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_module_interfaces",$this->ID,array(
				"type" => $this->Type,
				"module" => $this->Module,
				"title" => BigTree::safeEncode($this->Title),
				"table" => $this->Table,
				"settings" => (array) $this->Settings
			));

			AuditTrail::track("bigtree_module_interfaces",$this->ID,"updated");
		}

	}