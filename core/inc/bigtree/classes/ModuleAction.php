<?php
	/*
		Class: BigTree\ModuleAction
			Provides an interface for handling BigTree module actions.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read Module $Module
	 */
	
	class ModuleAction extends BaseObject
	{
		
		protected $ID;
		protected $Module;
		protected $OriginalRoute;
		
		public $Icon;
		public $InNav;
		public $Interface;
		public $Level;
		public $Name;
		public $Position;
		public $Route;
		
		/*
			Constructor:
				Builds a ModuleAction object referencing an existing database entry.

			Parameters:
				action - An array of action data
				module - The module for this action (passed by reference or passed as a module ID in the action array)
		*/
		
		public function __construct(array $action, ?Module &$module = null) {
			if (is_null($module) && !Module::exists($action["module"])) {
				trigger_error("The module for this action does not exist.", E_USER_ERROR);
			}
			
			$this->ID = $action["id"];
			$this->Module = !is_null($module) ? $module : new Module($action["module"]);
			
			$this->Icon = $action["class"];
			$this->InNav = $action["in_nav"] ? true : false;
			$this->Interface = $action["interface"] ?: false;
			$this->Level = $action["level"];
			$this->Name = $action["name"];
			$this->Position = $action["position"];
			$this->Route = $this->OriginalRoute = $action["route"];
		}
		
		/*
			Function: create
				Creates a module action.

			Parameters:
				module - The module ID to create an action for.
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
		
		public static function create(string $module, string $name, string $route, bool $in_nav, string $icon,
									  ?int $interface, int $level = 0, int $position = 0): ModuleAction
		{
			if (!Module::exists($module)) {
				trigger_error("The specified module does not exist.", E_USER_ERROR);
			}
			
			$subset = DB::getSubset("modules", $module);
			$route = $subset->unique("actions", "route", Link::urlify($route));
			$action = [
				"module" => $module,
				"name" => Text::htmlEncode($name),
				"route" => $route,
				"in_nav" => ($in_nav ? "on" : ""),
				"class" => $icon,
				"level" => intval($level),
				"interface" => $interface ?: null,
				"position" => $position
			];
			$id = $subset->insert("actions", $action);
			$action["id"] = $id;
			
			AuditTrail::track("config:modules", $module, "created-action");
			
			return new ModuleAction($action);
		}
		
		/*
			Function: delete
				Deletes the module action and the related interface (if no other action is using it).
		*/
		
		public function delete(): ?bool
		{
			$subset = DB::getSubset("modules", $this->Module->ID);
			
			// If this action is the only one using the interface, delete it as well
			if (!empty($this->Interface)) {
				$interface_count = 0;
			
				foreach ($this->Module->Interfaces as $interface) {
					if ($interface->ID == $this->Interface) {
						$interface_count++;
					}
				}
				
				if ($interface_count == 1) {
					$subset->delete("interfaces", $this->Interface);
				}
				
				AuditTrail::track("config:modules", $this->Module->ID, "deleted-interface");
			}
			
			$subset->delete("actions", $this->ID);
			AuditTrail::track("config:modules", $this->Module->ID, "deleted-action");
			
			return true;
		}

		/*
			Function: existsForRoute
				Checks to see if a module action exists for the given module and route.

			Parameters:
				module - A module ID
				route - A route

			Returns:
				true if an action exists
		*/

		public static function existsForRoute(string $module, string $route): bool
		{
			if (!DB::exists("modules", $module)) {
				return false;
			}

			$module = new Module($module);

			foreach ($module->Actions as $action) {
				if ($action->Route == $route) {
					return true;
				}
			}

			return false;
		}
		
		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the action or not.

			Returns:
				true if the user can access the action, otherwise false.
		*/
		
		public function getUserCanAccess(): bool
		{
			return Auth::user()->canAccess($this);
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			$subset = DB::getSubset("modules", $this->Module->ID);
			
			// Make sure route is unique and clean
			$this->Route = Link::urlify($this->Route);
			
			if ($this->Route != $this->OriginalRoute) {
				$this->Route = $subset->unique("actions", "route", $this->Route);
				$this->OriginalRoute = $this->Route;
			}
			
			$subset->update("actions", $this->ID, [
				"name" => Text::htmlEncode($this->Name),
				"route" => $this->Route,
				"class" => $this->Icon,
				"in_nav" => $this->InNav ? "on" : false,
				"level" => $this->Level,
				"position" => $this->Position,
				"interface" => $this->Interface ?: null
			]);
			
			AuditTrail::track("config:modules", $this->Module->ID, "updated-action");
			
			return true;
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
		
		public function update(string $name, string $route, bool $in_nav, string $icon, ?int $interface, int $level,
							   int $position): void
		{
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
	