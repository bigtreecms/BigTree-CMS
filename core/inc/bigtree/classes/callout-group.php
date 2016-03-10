
<?php
	/*
		Class: BigTree\CalloutGroup
			Provides an interface for handling BigTree callout groups.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class CalloutGroup extends BaseObject {

		protected $ID; // This shouldn't be editable from outside the class instance

		public $Callouts;
		public $Name;

		/*
			Constructor:
				Builds a CalloutGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (t
				o pull a record) or an array (to use the array as the record)
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
			Function: all
				Returns an array of callout groups sorted by name.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of BigTree\CalloutGroup objects.
		*/

		static function all($return_arrays = false) {
			$groups = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_callout_groups ORDER BY name ASC");

			// Convert to objects
			if (!$return_arrays) {
				foreach ($groups as &$group) {
					$group = new CalloutGroup($group);
				}
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

		static function create($name,$callouts = array()) {
			// Order callouts alphabetically by ID
			sort($callouts);

			// Insert group
			$id = BigTreeCMS::$DB->insert("bigtree_callout_groups",array(
				"name" => BigTree::safeEncode($name),
				"callouts" => $callouts
			));

			AuditTrail::track("bigtree_callout_groups",$id,"created");

			return new CalloutGroup($id);
		}

		/*
			Function: delete
				Deletes a callout group.

			Parameters:
				id - The id of the callout group.
		*/

		function delete() {
			BigTreeCMS::$DB->delete("bigtree_callout_groups",$this->ID);
			AuditTrail::track("bigtree_callout_groups",$this->ID,"deleted");
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			$this->Callouts = (array)$this->Callouts;
			sort($this->Callouts);

			BigTreeCMS::$DB->update("bigtree_callout_groups",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"callouts" => $this->Callouts
			));

			AuditTrail::track("bigtree_callout_groups",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the callout group's name and callout list properties and saves them back to the database.

			Parameters:
				name - Name string.
				callouts - An array of callout IDs to assign to the group.
		*/

		function update($name,$callouts) {
			$this->Name = $name;
			$this->Callouts = $callouts;
			$this->save();
		}

	}
