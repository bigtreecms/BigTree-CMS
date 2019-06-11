<?php
	/*
		Class: BigTree\ModuleInterface
			Provides an interface for handling BigTree module interfaces (views, forms, reports, etc).
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read Module $Module
	 */
	
	class ModuleInterface extends BaseObject {
		
		protected $ID;
		protected $Module;
		
		public $Settings;
		public $Table;
		public $Title;
		public $Type;
		
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
			]
		];
		public static $Plugins = [];
		
		/*
			Constructor:
				Builds a ModuleInterface object referencing an existing database entry.

			Parameters:
				interface - An array of interface data
				module - The module for this interface (passed by reference or passed as a module ID in the interface array)
		*/
		
		public function __construct(array $interface, ?Module &$module = null) 
		{
			if (is_null($module) && !Module::exists($interface["module"])) {
				trigger_error("The module for this interface does not exist.", E_USER_ERROR);
			}
			
			$this->ID = $interface["id"];
			$this->Module = !is_null($module) ? $module : new Module($interface["module"]);
			
			$this->Settings = Link::decode($interface["settings"]);
			$this->Table = $interface["table"];
			$this->Title = $interface["title"];
			$this->Type = $interface["type"];
		}
		
		/*
			Function: allByModuleAndType
				Returns an array of interfaces related to the given module.

			Parameters:
				module - The module or module ID to pull interfaces for (if null, returns all interfaces)
				type - The type of interface to return (defaults to null for all types)
				orderby - Field to order by (defaults to title)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of interfaces.
		*/
		
		public static function allByModuleAndType(?int $module = null, ?string $type = null,
												  string $orderby = "title", bool $return_arrays = false): array 
		{
			$interfaces = [];

			if (!is_null($module)) {
				$modules = [DB::get("modules", $module)];
			} else {
				$modules = DB::getAll("modules");
			}

			foreach ($modules as $module) {
				if (is_array($module["interfaces"])) {
					foreach ($module["interfaces"] as $interface) {
						if (is_null($type) || $interface["type"] == $type) {
							$interfaces[] = $interface;
						}
					}
				}
			}

			// Sort by the chosen column
			usort($interfaces, function($item, $item2) use ($orderby) {
				return strcmp($item[$orderby], $item2[$orderby]);
			});

			if ($return_arrays) {
				return $interfaces;
			}
			
			return array_map($interfaces, function($interface) { 
				return new ModuleInterface($interface); 
			});
		}
		
		/*
			Function: create
				Creates a module interface.

			Parameters:
				type - Interface type ("view", "form", "report", or an extension interface identifier)
				module - The module ID the interface is for
				title - The interface title (for admin purposes)
				table - The related table
				settings - An array of settings

			Returns:
				A ModuleInterface object.
		*/
		
		public static function create(string $type, string $module, string $title, string $table,
									  array $settings = []): ModuleInterface 
		{
			if (!DB::exists("modules", $module)) {
				trigger_error("Invalid module specified.", E_USER_ERROR);
			}
			
			$subset = DB::getSubset("modules", $module);
			$interface = [
				"module" => $module,
				"type" => $type,
				"title" => Text::htmlEncode($title),
				"table" => $table,
				"settings" => Link::encode($settings)
			];
			$id = $subset->insert("interfaces", $interface);
			$interface["id"] = $id;
			
			AuditTrail::track("config:modules", $module, "update", "created interface");
			
			return new ModuleInterface($interface);
		}
		
		/*
			Function: delete
				Deletes the module interface and the actions that use it.
		*/
		
		public function delete(): ?bool 
		{
			$subset = DB::getSubset("modules", $this->Module->ID);
			$subset->delete("interfaces", $this->ID);
			$subset->delete("actions", $this->ID, "interface");
			
			AuditTrail::track("config:modules", $this->Module->ID, "update", "deleted interface");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			$subset = DB::getSubset("modules", $this->Module->ID);
			
			// Some sub-classes use $this->Settings so we check for InterfaceSettings first when grabbing data.
			$subset->update("interfaces", $this->ID, [
				"type" => $this->Type,
				"module" => $this->Module,
				"title" => Text::htmlEncode($this->Title),
				"table" => $this->Table,
				"settings" => (array) (isset($this->InterfaceSettings) ? $this->InterfaceSettings : $this->Settings)
			]);
			
			AuditTrail::track("config:modules", $this->Module->ID, "update", "updated interface");
	
			return true;
		}

	}
	