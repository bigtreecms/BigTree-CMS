<?php
	/*
		Class: BigTree\Callout
			Provides an interface for handling BigTree callouts.
	*/

	namespace BigTree;

	class Callout extends BaseObject {

		public static $Table = "bigtree_callouts";

		public $Description;
		public $DisplayDefault;
		public $DisplayField;
		public $Extension;
		public $Fields;
		public $ID;
		public $Level;
		public $Name;
		public $Position;

		/*
			Constructor:
				Builds a Callout object referencing an existing database entry.

			Parameters:
				callout - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($callout = null) {
			if ($callout !== null) {
				// Passing in just an ID
				if (!is_array($callout)) {
					$callout = SQL::fetch("SELECT * FROM bigtree_callouts WHERE id = ?", $callout);
				}

				// Bad data set
				if (!is_array($callout)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $callout["id"];
					$this->Description = $callout["description"];
					$this->DisplayDefault = $callout["display_default"];
					$this->DisplayField = $callout["display_field"];
					$this->Extension = $callout["extension"];
					$this->Fields = is_string($callout["resources"]) ? json_decode($callout["resources"], true) : $callout["resources"];
					$this->Level = $callout["level"];
					$this->Name = $callout["name"];
					$this->Position = $callout["position"];
				}
			}
		}

		/*
			Function: allAllowed
				Returns a list of callouts the logged-in user is allowed access to.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		static function allAllowed($sort = "position DESC, id ASC", $return_arrays = false) {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				trigger_error("Method allAllowed not available outside logged-in user context.", E_USER_ERROR);

				return false;
			}

			$callouts = SQL::fetchAll("SELECT * FROM bigtree_callouts WHERE level <= ? ORDER BY $sort", $admin->Level);

			// Return objects
			if (!$return_arrays) {
				foreach ($callouts as &$callout) {
					$callout = new Callout($callout);
				}
			}

			return $callouts;
		}

		/*
			Function: allInGroups
				Returns a list of callouts in a given set of groups.

			Parameters:
				groups - An array of group IDs to retrieve callouts for.
				auth - If set to true, only returns callouts the logged in user has access to. Defaults to true.
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An alphabetized array of entries from the bigtree_callouts table.
		*/

		static function allInGroups($groups, $auth = true, $return_arrays = false) {
			global $admin;
			$ids = $callouts = $names = array();

			// Make sure a user is logged in
			if ($auth && (get_class($admin) != "BigTreeAdmin" || !$admin->ID)) {
				trigger_error("Method allInGroups not available outside logged-in user context when passing auth = true.", E_USER_ERROR);

				return false;
			}

			foreach ($groups as $group_id) {
				$group = new CalloutGroup($group_id);

				foreach ($group->Callouts as $callout_id) {
					// Only grab each callout once
					if (!in_array($callout_id, $ids)) {
						$callout = SQL::fetch("SELECT * FROM bigtree_callouts WHERE id = ?", $callout_id);
						$ids[] = $callout_id;

						// If we're looking at only the ones the user is allowed to access, check levels
						if (!$auth || $admin->Level >= $callout["level"]) {
							$callouts[] = $callout;
							$names[] = $callout["name"];
						}
					}
				}
			}
			
			array_multisort($names, $callouts);

			// Return objects
			if (!$return_arrays) {
				foreach ($callouts as &$callout) {
					$callout = new Callout($callout);
				}
			}

			return $callouts;
		}

		/*
			Function: create
				Creates a callout and its files.

			Parameters:
				id - The id.
				name - The name.
				description - The description.
				level - Access level (0 for everyone, 1 for administrators, 2 for developers).
				fields - An array of fields.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent

			Returns:
				A Callout object if successful, false if an invalid ID was passed or the ID is already in use
		*/

		static function create($id, $name, $description, $level, $fields, $display_field, $display_default) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-", "_"), "", $id)) || strlen($id) > 127) {
				return false;
			}

			// See if a callout ID already exists
			if (SQL::exists("bigtree_callouts", $id)) {
				return false;
			}

			// If we're creating a new file, let's populate it with some convenience things to show what fields are available.
			$file_contents = '<?php
	/*
		Fields Available:
';

			$cached_types = FieldType::reference();
			$types = $cached_types["callouts"];

			foreach ($fields as $key => $field) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if (!$field["id"] || $field["id"] == "type") {
					unset($fields[$key]);
				} else {
					$field = array(
						"id" => Text::htmlEncode($field["id"]),
						"type" => Text::htmlEncode($field["type"]),
						"title" => Text::htmlEncode($field["title"]),
						"subtitle" => Text::htmlEncode($field["subtitle"]),
						"options" => (array) @json_decode($field["options"], true)
					);

					// Backwards compatibility with BigTree 4.1 package imports
					foreach ($field as $k => $v) {
						if (!in_array($k, array("id", "title", "subtitle", "type", "options"))) {
							$field["options"][$k] = $v;
						}
					}

					$fields[$key] = $field;

					$file_contents .= '		"'.$field["id"].'" = '.$field["title"].' - '.$types[$field["type"]]["name"]."\n";
				}
			}

			$file_contents .= '	*/
?>';

			// Create the template file if it doesn't yet exist
			if (!file_exists(SERVER_ROOT."templates/callouts/$id.php")) {
				FileSystem::createFile(SERVER_ROOT."templates/callouts/$id.php", $file_contents);
			}

			// Increase the count of the positions on all templates by 1 so that this new template is for sure in last position.
			SQL::query("UPDATE bigtree_callouts SET position = position + 1");

			// Insert the callout
			SQL::insert("bigtree_callouts", array(
				"id" => Text::htmlEncode($id),
				"name" => Text::htmlEncode($name),
				"description" => Text::htmlEncode($description),
				"resources" => $fields,
				"level" => $level,
				"display_field" => $display_field,
				"display_default" => $display_default

			));

			AuditTrail::track("bigtree_callouts", $id, "created");

			return new Callout($id);
		}

		/*
			Function: delete
				Deletes the callout and removes its file.
		*/

		function delete() {
			$id = $this->ID;

			// Delete template file
			unlink(SERVER_ROOT."templates/callouts/$id.php");

			// Delete callout
			SQL::delete("bigtree_callouts", $id);

			// Remove the callout from any groups it lives in
			$groups = SQL::fetchAll("SELECT id, callouts FROM bigtree_callout_groups 
												 WHERE callouts LIKE '%\"".SQL::escape($id)."\"%'");
			foreach ($groups as $group) {
				$callouts = array_filter((array) json_decode($group["callouts"], true));
				// Remove this callout
				$callouts = array_diff($callouts, array($id));
				// Update DB
				SQL::update("bigtree_callout_groups", $group["id"], array("callouts" => $callouts));
			}

			// Track deletion
			AuditTrail::track("bigtree_callouts", $id, "deleted");
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Callouts set their own ID, so we need to check the database to see if the ID already exists before updating/creating
			if (SQL::exists("bigtree_callouts", $this->ID)) {
				// Clean up fields
				$fields = array();
				foreach ($this->Fields as $field) {
					// "type" is still a reserved keyword due to the way we save callout data when editing.
					if ($field["id"] && $field["id"] != "type") {
						$fields[] = array(
							"id" => Text::htmlEncode($field["id"]),
							"type" => Text::htmlEncode($field["type"]),
							"title" => Text::htmlEncode($field["title"]),
							"subtitle" => Text::htmlEncode($field["subtitle"]),
							"options" => json_decode($field["options"], true)
						);
					}
				}

				SQL::update("bigtree_callouts", $this->ID, array(
					"name" => Text::htmlEncode($this->Name),
					"description" => Text::htmlEncode($this->Description),
					"display_default" => $this->DisplayDefault,
					"display_field" => $this->DisplayField,
					"resources" => $fields,
					"level" => $this->Level,
					"position" => $this->Position,
					"extension" => $this->Extension
				));
				AuditTrail::track("bigtree_callouts", $this->ID, "updated");
			} else {
				$new = static::create($this->ID, $this->Name, $this->Description, $this->Level, $this->Fields, $this->DisplayField, $this->DisplayDefault);

				if ($new !== false) {
					$this->inherit($new);
				} else {
					trigger_error("Failed to create new callout object due to invalid or used ID.", E_USER_WARNING);
				}
			}
		}

		/*
			Function: update
				Updates the callout properties and saves changes to the database.

			Parameters:
				name - The name.
				description - The description.
				level - The access level (0 for all users, 1 for administrators, 2 for developers)
				fields - An array of fields.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent
		*/

		function update($name, $description, $level, $fields, $display_field, $display_default) {
			$this->Name = $name;
			$this->Description = $description;
			$this->Level = $level;
			$this->Fields = $fields;
			$this->DisplayField = $display_field;
			$this->DisplayDefault = $display_default;

			$this->save();
		}

	}
