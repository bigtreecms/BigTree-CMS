<?php
	/*
		Class: BigTree\Module
			Provides an interface for handling BigTree modules.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read ModuleAction[] $Actions
	 * @property-read ModuleForm[] $Forms
	 * @property-read int $ID
	 * @property-read ModuleInterface[] $Interfaces
	 * @property-read array $Navigation
	 * @property-read ModuleReport[] $Reports
	 * @property-read string $Table
	 * @property-read array $UserAccessibleGroups
	 * @property-read string $UserAccessLevel
	 * @property-read bool $UserCanAccess
	 * @property-read ModuleView[] $Views
	 */
	
	class Module extends JSONObject
	{
		
		protected $Actions = [];
		protected $Forms = [];
		protected $ID;
		protected $Interfaces = [];
		protected $Reports = [];
		protected $Views = [];
		
		public $Class;
		public $DeveloperOnly;
		public $Extension;
		public $Group;
		public $GroupBasedPermissions = [];
		public $Name;
		public $Position;
		public $Route;
		
		public static $CachesBuilt = false;
		public static $ClassCache = [];
		public static $ReservedColumns = [
			"id",
			"position",
			"archived",
			"approved"
		];
		public static $Store = "modules";
		
		/*
			Constructor:
				Builds a Module object referencing an existing database entry.

			Parameters:
				module - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
		*/
		
		public function __construct($module = null, ?callable $on_fail = null)
		{
			if ($module !== null) {
				// Passing in just an ID
				if (is_string($module)) {
					$module = DB::get("modules", $module);
				}
				
				// Bad data set
				if (!is_array($module)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					$this->Class = $module["class"];
					$this->DeveloperOnly = $module["developer_only"];
					$this->Extension = $module["extension"];
					$this->Group = $module["group"] ?: false;
					$this->GroupBasedPermissions = $module["gbp"];
					$this->ID = $module["id"];
					$this->Name = $module["name"];
					$this->Position = intval($module["position"]);
					$this->Route = $module["route"];
					
					if (is_array($module["actions"])) {
						foreach ($module["actions"] as $action) {
							$this->Actions[$action["id"]] = new ModuleAction($action, $this);
						}
					}
					
					if (is_array($module["interfaces"])) {
						foreach ($module["interfaces"] as $interface) {
							if ($interface["type"] == "view") {
								$this->Views[$interface["id"]] = new ModuleView($interface, $this);
							} elseif ($interface["type"] == "form") {
								$this->Forms[$interface["id"]] = new ModuleForm($interface, $this);
							} elseif ($interface["type"] == "report") {
								$this->Reports[$interface["id"]] = new ModuleReport($interface, $this);
							}
							
							$this->Interfaces[$interface["id"]] = new ModuleInterface($interface, $this);
						}
					}
				}
			}
		}
		
		/*
			Function: actionExistsForRoute
				Checks to see if an action exists for a given route and module.

			Parameters:
				route - The route of the action to check.

			Returns:
				true if an action exists, otherwise false.
		*/
		
		public function actionExistsForRoute(string $route): bool
		{
			foreach ($this->Actions as $action) {
				if ($action["route"] == $route) {
					return true;
				}
			}
			
			return false;
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
				An array of Module objects or null if the group does not exist.
		*/
		
		public static function allByGroup(?string $group = null, string $sort = "position DESC, id ASC",
										  bool $return_arrays = false, bool $auth = true): ?array
		{
			if (!empty($group) && !DB::exists("module-groups", $group)) {
				return null;
			}
			
			$modules = [];
			$all = static::all("position");
			
			foreach ($all as $module) {
				if ($module->Group == $group || (empty($group) && empty($module->Group))) {
					if (!$auth || $module->UserCanAccess) {
						$modules[$module->ID] = $return_arrays ? $module->Array : $module;
					}
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
				route - Desired route to use (defaults to auto generating if this is left empty).
				developer_only - Sets the module to be only accessible/visible to developers (defaults to false).

			Returns:
				A Module object or null if an invalid route was passed.
		*/
		
		public static function create(string $name, string $group, string $class, string $table, ?array $permissions,
									  ?string $route = null, bool $developer_only = false): ?Module
		{
			// Find an available module route.
			$route = !is_null($route) ? $route : Link::urlify($name);
			
			if (!ctype_alnum(str_replace("-", "", $route)) || strlen($route) > 127) {
				return null;
			}
			
			// Go through the hard coded modules
			$existing = [];
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
					$existing[] = substr($f, 0, -4);
				}
			}
			
			// Go through already created modules
			$existing_modules = DB::getAll("modules");

			foreach ($existing_modules as $existing_module) {
				$existing[] = $existing_module["route"];
			}
			
			// Get a unique route
			$x = 2;
			$original_route = $route;
			
			while (in_array($route, $existing)) {
				$route = $original_route."-".$x;
				$x++;
			}
			
			// Create class module if a class name was provided
			if ($class && !file_exists(SERVER_ROOT."custom/inc/modules/$route.php")) {
				// Class file
				FileSystem::createFile(SERVER_ROOT."custom/inc/modules/$route.php", '<?php
	class '.$class.' extends BigTreeModule {
	
		public static $RouteRegistry = [];
		
		public $Table = "'.$table.'";
		
	}
');
				// Remove cached class list.
				FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			}
			
			// Create it
			$id = DB::insert("modules", [
				"name" => Text::htmlEncode($name),
				"route" => $route,
				"class" => $class,
				"group" => $group ?: null,
				"gbp" => $permissions ?: [],
				"developer_only" => ($developer_only ? "on" : ""),
				"interfaces" => [],
				"actions" => []
			]);
			
			AuditTrail::track("config:modules", $id, "add", "created");
			
			return new Module($id);
		}
		
		/*
			Function: delete
				Deletes the module, all related module actions, interfaces, directories, and class files.
		*/
		
		public function delete(): ?bool
		{
			if (!DB::exists("modules", $this->ID)) {
				return false;
			}
			
			// Delete class file and custom directory
			FileSystem::deleteFile(SERVER_ROOT."custom/inc/modules/".$this->Route.".php");
			FileSystem::deleteDirectory(SERVER_ROOT."custom/admin/modules/".$this->Route."/");
			
			DB::delete("modules", $this->ID);
			AuditTrail::track("config:modules", $this->ID, "delete", "deleted");
			
			return true;
		}
		
		/*
			Function: getActionByInterface
				Returns the module action for a given module interface.
				Prioritizes edit action over add.
		
			Parameters:
				interface - The ID of an interface, interface array or interface object.

			Returns:
				A module action entry or false if none exists for the provided interface.
		*/
		
		public function getActionByInterface($interface): ?ModuleAction
		{
			if (is_object($interface)) {
				$id = $interface->ID;
			} elseif (is_array($interface)) {
				$id = $interface["id"];
			} else {
				$id = $interface;
			}
			
			foreach ($this->Actions as $action) {
				if ($action->Interface == $id) {
					return $action;
				}
			}
			
			return null;
		}
		
		/*
			Function: getActionForPath
				Returns a ModuleAction for the given module and path.

			Parameters:
				path - The path of the action and additional commands.

			Returns:
				An array containing the action and additional commands or false if lookup failed.
		*/
		
		public function getActionForPath(array $path): ?array
		{
			// For landing routes.
			if (!count($path)) {
				$path = [""];
			}
			
			$commands = [];
			
			while (count($path)) {
				$route_string = implode("/", $path);
				
				foreach ($this->Actions as $action) {
					if ($action->Route == $route_string) {
						return [
							"action" => $action,
							"commands" => array_reverse($commands)
						];
					}
				}
				
				$commands[] = array_pop($path);
			}
			
			return null;
		}
		
		/*
			Function: getEditAction
				Returns a ModuleAction for this module and the given form ID.

			Parameters:
				form - Form ID

			Returns:
				A ModuleAction object or null if none exists.
		*/
		
		public function getEditAction(string $form): ?ModuleAction
		{
			foreach ($this->Actions as $action) {
				if (substr($action->Route, 0, 4) == "edit" && $action->Interface->ID == $form) {
					return $action;
				}
			}
			
			return null;
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
		
		public function getGroupAccessLevel(string $group): string
		{
			return Auth::user()->getGroupAccessLevel($this, $group);
		}
		
		/*
			Function: getNavigation
				Returns an array of module actions that are in navigation.

			Returns:
				An array of ModuleAction objects.
		*/
		
		public function getNavigation(): array
		{
			$nav = [];
			
			foreach ($this->Actions as $action) {
				if ($action->InNav) {
					$nav[] = $action;
				}
			}
			
			return $nav;
		}
		
		/*
			Function: getUserAccessibleGroups
				Returns an array of all groups the logged in user has access to in this module.

			Returns:
				An array of groups if a user has limited access to a module or "true" if the user has access to all groups.
		*/
		
		public function getUserAccessibleGroups(): array
		{
			return Auth::user()->getAccessibleModuleGroups($this);
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the module

			Returns:
				A permission level ("p" for publisher, "e" for editor, null for none)
		*/
		
		public function getUserAccessLevel(): ?string
		{
			return Auth::user()->getAccessLevel($this);
		}
		
		/*
			Function: getUserAccessLevelForEntry
				Returns the permission level for a given module and item.
				Can be called non-statically to check for the logged in user.

			Parameters:
				item - The entry of the module to check access for.
				table - The group based table.
				user - (optional) User object to check permissions for (defaults to logged in user)

			Returns:
				The permission level for the given item or module (if item was not passed).

		*/
		
		public function getUserAccessLevelForEntry(array $entry, string $table = "", ?User $user = null): string
		{
			return Auth::user($user)->getAccessLevel($this, $entry, $table);
		}
		
		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the module or not.

			Returns:
				true if the user can access the module, otherwise false.
		*/
		
		public function getUserCanAccess(): bool
		{
			return Auth::user()->canAccess($this);
		}
		
		/*
			Function: runParser
				Evaluates code in a function scope with $item and $value
				Used mostly internally in the admin for parsers.

			Parameters:
				item - Full array of data
				value - The value to be manipulated and returned
				code - The code to be run in eval()

			Returns:
				Modified $value
		*/
		
		public static function runParser(array $item, $value, string $code)
		{
			eval($code);
			
			return $value;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create(
					$this->Name,
					$this->Group,
					$this->Class,
					$this->Table,
					$this->GroupBasedPermissions,
					!empty($this->Route) ? $this->Route : false,
					!empty($this->DeveloperOnly) ? true : false
				);
				
				if ($new !== false) {
					$this->inherit($new);
					
					return true;
				} else {
					trigger_error("Failed to create module due to invalid route.", E_USER_WARNING);
					
					return null;
				}
			} else {
				DB::update("modules", $this->ID, [
					"group" => $this->Group,
					"name" => Text::htmlEncode($this->Name),
					"route" => DB::unique("modules", "route", Link::urlify($this->Route), $this->ID),
					"class" => $this->Class,
					"position" => $this->Position,
					"gbp" => array_filter((array) $this->GroupBasedPermissions),
					"developer_only" => $this->DeveloperOnly ? "on" : ""
				]);
				AuditTrail::track("config:modules", $this->ID, "update", "updated");
				
				// Clear cache file in case class or route changed
				@unlink(SERVER_ROOT."cache/bigtree-module-cache.json");
				
				// If this has a permissions table for group based permissions, wipe that table's view cache
				if ($this->GroupBasedPermissions["table"]) {
					ModuleView::clearCacheForTable($this->GroupBasedPermissions["table"]);
				}
				
				return true;
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
				developer_only - Sets a module to be accessible/visible to only developers.
		*/
		
		public function update(string $name, string $group, string $class, array $permissions,
							   bool $developer_only = false): ?bool
		{
			$this->Name = $name;
			$this->Group = $group ?: null;
			$this->Class = $class;
			$this->GroupBasedPermissions = $permissions;
			$this->DeveloperOnly = $developer_only;
			
			return $this->save();
		}
		
	}
	