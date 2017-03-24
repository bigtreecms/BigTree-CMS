<?php
	/*
		Class: BigTree\ModuleInterface
			Provides an interface for handling BigTree module interfaces (views, forms, reports, etc).
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class ModuleInterface extends BaseObject {
		
		public static $CoreTypes = [
			"views" => [
				"name" => "View",
				"icon" => "category",
				"description" => "Views are lists of database content. Views can have associated actions such as featuring, archiving, approving, editing, and deleting content."
			],
			"reports" => [
				"name" => "Report",
				"icon" => "graph",
				"description" => "Reports allow your admin users to filter database content. Reports can either generate a filtered view (based on an existing View interface) or export the data to a CSV."
			],
			"forms" => [
				"name" => "Form",
				"icon" => "form",
				"description" => "Forms are used for creating and editing database content by admin users."
			],
			"embeds" => [
				"name" => "Embeddable Form",
				"icon" => "file_default",
				"description" => "Embeddable forms allow your front-end users to create database content using your existing field types via iframes."
			]
		];
		public static $Plugins = [];
		public static $Table = "bigtree_module_interfaces";
		
		protected $ID;
		
		public $Module;
		public $Settings;
		public $Title;
		public $Type;
		
		/*
			Constructor:
				Builds a ModuleInterface object referencing an existing database entry.

			Parameters:
				interface - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		function __construct($interface = null) {
			if ($interface !== null) {
				// Passing in just an ID
				if (!is_array($interface)) {
					$interface = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
				}
				
				// Bad data set
				if (!is_array($interface)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $interface["id"];
					
					$this->Module = $interface["module"];
					$this->Settings = is_array($interface["settings"]) ? $interface["settings"] : (array) @json_decode($interface["settings"], true);
					$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the parent class
					$this->Title = $interface["title"];
					$this->Type = $interface["type"];
				}
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
		
		static function allByModuleAndType(?int $module = null, ?string $type = null, string $order = "`title` ASC",
										   bool $return_arrays = false): array {
			$where = $parameters = [];
			
			// Add module restriction
			if (!is_null($module)) {
				$where[] = "`module` = ?";
				$parameters[] = is_array($module) ? $module["id"] : $module;
			}
			
			// Add type restriciton
			if ($type !== false) {
				$where[] = "`type` = ?";
				$parameters[] = $type;
			}
			
			// Add the query
			$where = count($where) ? " WHERE ".implode(" AND ", $where) : "";
			
			// Push query onto parameters
			array_unshift($parameters, "SELECT * FROM bigtree_module_interfaces $where ORDER BY $order");
			
			// Get the arrays
			$interfaces = call_user_func_array(["BigTree\\SQL", "fetchAll"], $parameters);
			
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
		
		static function create(string $type, int $module, string $title, string $table, array $settings = []): ModuleInterface {
			$id = SQL::insert("bigtree_module_interfaces", [
				"type" => $type,
				"module" => intval($module),
				"title" => Text::htmlEncode($title),
				"table" => $table,
				"settings" => $settings
			]);
			
			AuditTrail::track("bigtree_module_interfaces", $id, "created");
			
			return new ModuleInterface($id);
		}
		
		/*
			Function: delete
				Deletes the module interface and the actions that use it.
		*/
		
		function delete(): ?bool {
			SQL::delete("bigtree_module_actions", ["interface" => $this->ID]);
			SQL::delete("bigtree_module_interfaces", $this->ID);
			
			AuditTrail::track("bigtree_module_interfaces", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		function save(): ?bool {
			if (empty($this->ID)) {
				$new = static::create($this->Type, $this->Module, $this->Title, $this->Table, $this->Settings);
				$this->inherit($new);
			} else {
				// Some sub-classes use $this->Settings so we check for InterfaceSettings first when grabbing data.
				SQL::update("bigtree_module_interfaces", $this->ID, [
					"type" => $this->Type,
					"module" => $this->Module,
					"title" => Text::htmlEncode($this->Title),
					"table" => $this->Table,
					"settings" => (array) (isset($this->InterfaceSettings) ? $this->InterfaceSettings : $this->Settings)
				]);
				
				AuditTrail::track("bigtree_module_interfaces", $this->ID, "updated");
			}
			
			return true;
		}
		
	}
	