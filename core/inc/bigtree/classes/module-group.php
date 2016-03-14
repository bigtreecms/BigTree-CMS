<?php
	/*
		Class: BigTree\ModuleGroup
			Provides an interface for handling BigTree module groups.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

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
				$group = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_groups WHERE id = ?", $group);
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

		function create($name) {
			$id = BigTreeCMS::$DB->insert("bigtree_module_groups",array(
				"name" => BigTree::safeEncode($name),
				"route" => BigTreeCMS::$DB->unique("bigtree_module_groups","route",BigTreeCMS::urlify($name))
			));

			AuditTrail::track("bigtree_module_groups",$id,"created");
			
			return new ModuleGroup($id);
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_module_groups",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"route" => BigTreeCMS::$DB->unique("bigtree_module_groups","route",BigTreeCMS::urlify($this->Route),$this->ID)
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
			$this->Route = BigTreeCMS::urlify($name);
			$this->save();
		}

	}