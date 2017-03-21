<?php
	/*
		Class: BigTree\Module
			Provides an interface for handling BigTree modules.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read array $Navigation
	 * @property-read string $Table
	 * @property-read array $UserAccessibleGroups
	 * @property-read string $UserAccessLevel
	 * @property-read bool $UserCanAccess
	 */
	
	class Module extends BaseObject {
		
		public static $CachesBuilt = false;
		public static $ClassCache = [];
		public static $IconClasses = ["add", "delete", "list", "edit", "refresh", "gear", "truck", "token", "export", "redirect", "help", "error", "ignored", "world", "server", "clock", "network", "car", "key", "folder", "calendar", "search", "setup", "page", "computer", "picture", "news", "events", "blog", "form", "category", "map", "done", "warning", "user", "question", "sports", "credit_card", "cart", "cash_register", "lock_key", "bar_graph", "comments", "email", "weather", "pin", "planet", "mug", "atom", "shovel", "cone", "lifesaver", "target", "ribbon", "dice", "ticket", "pallet", "lightning", "camera", "video", "twitter", "facebook", "trail", "crop", "cloud", "phone", "music", "house", "featured", "heart", "link", "flag", "bug", "games", "coffee", "airplane", "bank", "gift", "badge", "award", "radio"];
		public static $ReservedColumns = [
			"id",
			"position",
			"archived",
			"approved"
		];
		public static $Table = "bigtree_modules";
		
		protected $ID;
		
		public $Class;
		public $DeveloperOnly;
		public $Extension;
		public $Group;
		public $GroupBasedPermissions = [];
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
		
		function __construct($module = null) {
			if ($module !== null) {
				// Passing in just an ID
				if (!is_array($module)) {
					$module = SQL::fetch("SELECT * FROM bigtree_modules WHERE id = ?", $module);
				}
				
				// Bad data set
				if (!is_array($module)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $module["id"];
					
					$this->Class = $module["class"];
					$this->DeveloperOnly = $module["developer_only"];
					$this->Extension = $module["extension"];
					$this->Group = $module["group"] ?: false;
					$this->Icon = $module["icon"];
					$this->Name = $module["name"];
					$this->Position = $module["position"];
					$this->Route = $module["route"];
					
					$gbp = is_string($module["gbp"]) ? @json_decode($module["gbp"], true) : $module["gbp"];
					$this->GroupBasedPermissions = is_array($gbp) ? $gbp : [];
				}
			}
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
		
		static function allByGroup(string $group, string $sort = "position DESC, id ASC", bool $return_arrays = false,
								   bool $auth = true): array {
			$modules = [];
			
			if ($group) {
				$results = SQL::fetchAll("SELECT * FROM bigtree_modules WHERE `group` = ? ORDER BY $sort", $group);
			} else {
				$results = SQL::fetchAll("SELECT * FROM bigtree_modules WHERE `group` = 0 OR `group` IS NULL ORDER BY $sort");
			}
			
			foreach ($results as $module_array) {
				$module = new Module($module_array);
				
				// Check auth
				if (!$auth || $module->UserCanAccess) {
					$modules[$module_array["id"]] = $return_arrays ? $module_array : $module;
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
				A Module object or null if an invalid route was passed.
		*/
		
		static function create(string $name, string $group, string $class, string $table, ?array $permissions,
							   string $icon, ?string $route = null, bool $developer_only = false): ?Module {
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
			array_merge($existing, SQL::fetchAllSingle("SELECT route FROM bigtree_modules"));
			
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
		public static $RouteRegistry = array();
		
		public $Table = "'.$table.'";
	}
');
				// Remove cached class list.
				FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			}
			
			// Create it
			$id = SQL::insert("bigtree_modules", [
				"name" => Text::htmlEncode($name),
				"route" => $route,
				"class" => $class,
				"icon" => $icon,
				"group" => $group ?: null,
				"gbp" => $permissions ?: "",
				"developer_only" => ($developer_only ? "on" : "")
			]);
			
			AuditTrail::track("bigtree_modules", $id, "created");
			
			return new Module($id);
		}
		
		/*
			Function: delete
				Deletes the module, all related module actions, interfaces, directories, and class files.
		*/
		
		function delete(): ?bool {
			// Delete class file and custom directory
			FileSystem::deleteFile(SERVER_ROOT."custom/inc/modules/".$this->Route.".php");
			FileSystem::deleteDirectory(SERVER_ROOT."custom/admin/modules/".$this->Route."/");
			
			// Delete all the related auto module actions
			SQL::delete("bigtree_module_interfaces", ["module" => $this->ID]);
			
			// Delete actions
			SQL::delete("bigtree_module_actions", ["module" => $this->ID]);
			
			// Delete the module
			SQL::delete("bigtree_modules", $this->ID);
			
			AuditTrail::track("bigtree_modules", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: getEditAction
				Returns a ModuleAction for this module and the given form ID.

			Parameters:
				form - Form ID

			Returns:
				A ModuleAction object or null if none exists.
		*/
		
		function getEditAction(string $form): ?ModuleAction {
			$action = SQL::fetch("SELECT * FROM bigtree_module_actions 
								  WHERE interface = ? AND module = ? AND route LIKE 'edit%'", $form, $this->ID);
			
			return $action ? new ModuleAction($action) : null;
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
		
		function getGroupAccessLevel(string $group): string {
			return Auth::user()->getGroupAccessLevel($this, $group);
		}
		
		/*
			Function: getNavigation
				Returns an array of module actions that are in navigation.

			Returns:
				An array of ModuleAction objects.
		*/
		
		function getNavigation(): array {
			$actions = SQL::fetchAll("SELECT * FROM bigtree_module_actions WHERE module = ? AND in_nav = 'on' 
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
		
		function getUserAccessibleGroups(): array {
			return Auth::user()->getAccessibleModuleGroups($this);
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the module

			Returns:
				A permission level ("p" for publisher, "e" for editor, "n" for none)
		*/
		
		function getUserAccessLevel(): string {
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
		
		function getUserAccessLevelForEntry(array $entry, string $table = "", ?User $user = null): string {
			return Auth::user($user)->getAccessLevel($this, $entry, $table);
		}
		
		/*
			Function: getUserCanAccess
				Determines whether the logged in user has access to the module or not.

			Returns:
				true if the user can access the module, otherwise false.
		*/
		
		function getUserCanAccess(): bool {
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
		
		static function runParser(array $item, $value, string $code) {
			eval($code);
			
			return $value;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		function save(): ?bool {
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
				SQL::update("bigtree_modules", $this->ID, [
					"group" => $this->Group,
					"name" => Text::htmlEncode($this->Name),
					"route" => SQL::unique("bigtree_modules", "route", Link::urlify($this->Route), $this->ID),
					"class" => $this->Class,
					"icon" => $this->Icon,
					"position" => $this->Position,
					"gbp" => array_filter((array) $this->GroupBasedPermissions),
					"developer_only" => $this->DeveloperOnly ? "on" : ""
				]);
				AuditTrail::track("bigtree_modules", $this->ID, "updated");
				
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
				icon - The icon to use.
				developer_only - Sets a module to be accessible/visible to only developers.
		*/
		
		function update(string $name, string $group, string $class, array $permissions, string $icon,
						bool $developer_only = false): ?bool {
			$this->Name = $name;
			$this->Group = $group ?: null;
			$this->Class = $class;
			$this->GroupBasedPermissions = $permissions;
			$this->Icon = $icon;
			$this->DeveloperOnly = $developer_only;
			
			return $this->save();
		}
		
	}
	
