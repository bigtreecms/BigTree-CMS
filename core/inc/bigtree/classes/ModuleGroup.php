<?php
	/*
		Class: BigTree\ModuleGroup
			Provides an interface for handling BigTree module groups.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 */
	
	class ModuleGroup extends BaseObject
	{
		
		protected $ID;
		
		public $Name;
		public $Position;
		public $Route;
		
		public static $Table = "bigtree_module_groups";
		
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
					$group = SQL::fetch("SELECT * FROM bigtree_module_groups WHERE id = ?", $group);
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
			$id = SQL::insert("bigtree_module_groups", [
				"name" => Text::htmlEncode($name),
				"route" => SQL::unique("bigtree_module_groups", "route", Link::urlify($name))
			]);
			
			AuditTrail::track("bigtree_module_groups", $id, "created");
			
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
				SQL::update("bigtree_module_groups", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"route" => SQL::unique("bigtree_module_groups", "route", Link::urlify($this->Route), $this->ID)
				]);
				
				AuditTrail::track("bigtree_module_groups", $this->ID, "updated");
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
	