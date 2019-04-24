<?php
	/*
		Class: BigTree\ModuleGroup
			Provides an interface for handling BigTree module groups.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class ModuleGroup extends JSONObject
	{
		
		protected $ID;
		
		public $Name;
		public $Position;
		public $Route;
		
		public static $Store = "module-groups";
		
		/*
			Constructor:
				Builds a ModuleGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($group = null)
		{
			if ($group !== null) {
				// Passing in just an ID
				if (!is_array($group)) {
					$group = DB::get("module-groups", $group);
				}
				
				// Bad data set
				if (!is_array($group)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $group["id"];
					
					$this->Name = $group["name"];
					$this->Position = $group["position"];
					$this->Route = $group["route"];
				}
			}
		}
		
		/*
			Function: create
				Creates a module group.

			Parameters:
				name - The name of the group.

			Returns:
				A ModuleGroup object.
		*/
		
		public static function create(string $name): ModuleGroup
		{
			$id = DB::insert("module-groups", [
				"name" => Text::htmlEncode($name),
				"route" => DB::unique("module-groups", "route", Link::urlify($name))
			]);
			
			AuditTrail::track("config:module-groups", $id, "created");
			
			return new ModuleGroup($id);
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create($this->Name);
				$this->inherit($new);
			} else {
				DB::update("module-groups", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"route" => DB::unique("module-groups", "route", Link::urlify($this->Route), $this->ID)
				]);
				
				AuditTrail::track("config:module-groups", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the module group's name and updates the route to match.

			Parameters:
				name - The name of the module group.
		*/
		
		public function update(string $name): ?bool
		{
			$this->Name = $name;
			$this->Route = Link::urlify($name);
			
			return $this->save();
		}
		
	}
	