<?php
	/*
		Class: BigTree\ModuleGroup
			Provides an interface for handling BigTree module groups.
	*/

	namespace BigTree;

	use BigTree;

	class ModuleGroup extends BaseObject {

		static $Table = "bigtree_module_groups";

		protected $ID;

		public $Name;
		public $Position;
		public $Route;


		/*
			Constructor:
				Builds a ModuleGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($group) {
			// Passing in just an ID
			if (!is_array($group)) {
				$group = SQL::fetch("SELECT * FROM bigtree_module_groups WHERE id = ?", $group);
			}

			// Bad data set
			if (!is_array($group)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $group["id"];

				$this->Name = $group["name"];
				$this->Position = $group["position"];
				$this->Route = $group["route"];
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

		static function create($name) {
			$id = SQL::insert("bigtree_module_groups",array(
				"name" => BigTree::safeEncode($name),
				"route" => SQL::unique("bigtree_module_groups","route",Link::urlify($name))
			));

			AuditTrail::track("bigtree_module_groups",$id,"created");
			
			return new ModuleGroup($id);
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			SQL::update("bigtree_module_groups",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"route" => SQL::unique("bigtree_module_groups","route",Link::urlify($this->Route),$this->ID)
			));

			AuditTrail::track("bigtree_module_groups",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the module group's name and updates the route to match.

			Parameters:
				name - The name of the module group.
		*/

		function update($name) {
			$this->Name = $name;
			$this->Route = Link::urlify($name);
			$this->save();
		}

	}