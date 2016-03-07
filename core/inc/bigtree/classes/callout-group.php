<?php
	/*
		Class: BigTree\CalloutGroup
			Provides an interface for handling BigTree callout groups.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class CalloutGroup {

		protected $ID; // This shouldn't be editable from outside the class instance

		public $Callouts;
		public $Name;

		/*
			Constructor:
				Builds a CalloutGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($group) {
			// Passing in just an ID
			if (!is_array($group)) {
				$group = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_callout_groups WHERE id = ?", $group);
			}

			// Bad data set
			if (!is_array($group)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $group["id"];
				$this->Name = $group["name"];
				$this->Callouts = array_filter((array) (is_string($group["callouts"]) ? json_decode($group["callouts"],true) : $group["callouts"]));
			}
		}

		/*
			Get Magic Method:
				Allows retrieval of the write-protected ID property.
		*/

		function __get($property) {
			if ($property == "ID") {
				return $this->ID;
			}
		}

		/*
			Function: all
				Returns an array of callout groups sorted by name.

			Returns:
				An array of BigTree\CalloutGroup objects.
		*/

		static function all() {
			$groups = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_callout_groups ORDER BY name ASC");

			// Convert to objects
			foreach ($groups as &$group) {
				$group = new CalloutGroup($group);
			}

			return $groups;
		}

		/*
			Function: create
				Creates a callout group.

			Parameters:
				name - The name of the group.
				callouts - An array of callout IDs to assign to the group.

			Returns:
				The id of the newly created group.
		*/

		static function create($name,$callouts) {
			// Order callouts alphabetically by ID
			sort($callouts);

			// Insert group
			$id = BigTreeCMS::$DB->insert("bigtree_callout_groups",array(
				"name" => BigTree::safeEncode($name),
				"callouts" => $callouts
			));

			BigTree\AuditTrail::track("bigtree_callout_groups",$id,"created");

			return new BigTree\CalloutGroup($id);
		}

		/*
			Function: delete
				Deletes a callout group.

			Parameters:
				id - The id of the callout group.
		*/

		function delete() {
			BigTreeCMS::$DB->delete("bigtree_callout_groups",$this->ID);
			BigTree\AuditTrail::track("bigtree_callout_groups",$this->ID,"deleted");
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_callout_groups",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"callouts" => $this->Callouts
			));

			BigTree\AuditTrail::track("bigtree_callout_groups",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the callout group's name and callout list properties and saves them back to the database.

			Parameters:
				name - Name string.
				callouts - An array of callout IDs to assign to the group.
		*/

		function update($name,$callouts) {
			sort($callouts);

			$this->Name = $name;
			$this->Callouts = $callouts;
			$this->save();
		}

	}
