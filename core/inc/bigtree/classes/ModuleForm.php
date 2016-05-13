<?php
	/*
		Class: BigTree\ModuleForm
			Provides an interface for handling BigTree module forms.
	*/

	namespace BigTree;

	class ModuleForm extends BaseObject {

		protected $ID;
		protected $Interface;

		public $DefaultPosition;
		public $Fields;
		public $Hooks;
		public $Module;
		public $ReturnURL;
		public $ReturnView;
		public $Tagging;
		public $Title;

		/*
			Constructor:
				Builds a ModuleForm object referencing an existing database entry.

			Parameters:
				interface - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($interface) {
			// Passing in just an ID
			if (!is_array($interface)) {
				$interface = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
			}

			// Bad data set
			if (!is_array($interface)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$this->ID = $interface["id"];
				$this->Interface = new ModuleInterface($interface);

				$this->DefaultPosition = $this->Interface->Settings["default_position"];
				$this->Fields = $this->Interface->Settings["fields"];
				$this->Hooks = array_filter((array) $this->Interface->Settings["hooks"]);
				$this->Module = $interface["module"];
				$this->ReturnURL = $this->Interface->Settings["return_url"];
				$this->ReturnView = $this->Interface->Settings["return_view"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Tagging = $this->Interface->Settings["tagging"] ? true : false;
				$this->Title = $interface["title"];
			}
		}

		/*
			Function: create
				Creates a module form.

			Parameters:
				module - The module ID that this form relates to.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				return_view - The view to return to after completing the form.
				return_url - The alternative URL to return to after completing the form.
				tagging - Whether or not to enable tagging.

			Returns:
				A ModuleForm object.
		*/

		static function create($module, $title, $table, $fields, $hooks = array(), $default_position = "", $return_view = false, $return_url = "", $tagging = "") {
			// Clean up fields for backwards compatibility
			foreach ($fields as $key => $data) {
				$field = array(
					"column" => $data["column"] ? $data["column"] : $key,
					"type" => Text::htmlEncode($data["type"]),
					"title" => Text::htmlEncode($data["title"]),
					"subtitle" => Text::htmlEncode($data["subtitle"]),
					"options" => is_array($data["options"]) ? $data["options"] : array_filter((array) json_decode($data["options"], true))
				);

				// Backwards compatibility with BigTree 4.1 package imports
				foreach ($data as $sub_key => $value) {
					if (!in_array($sub_key, array("title", "subtitle", "type", "options"))) {
						$field["options"][$sub_key] = $value;
					}
				}

				$fields[$key] = $field;
			}

			// Create the parent interface
			$interface = ModuleInterface::create("form", $module, $title, $table, array(
				"fields" => $fields,
				"default_position" => $default_position,
				"return_view" => $return_view,
				"return_url" => $return_url ? Link::encode($return_url) : "",
				"tagging" => $tagging ? "on" : "",
				"hooks" => is_string($hooks) ? json_decode($hooks, true) : $hooks
			));

			// Get related views for this table and update numeric status
			$view_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($view_ids as $view_id) {
				$view = new ModuleView($view_id);
				$view->refreshNumericColumns();
			}

			return new ModuleForm($interface->Array);
		}

		/*
			Function: createEntry
				Creates an entry in the database for this form.

			Parameters:
				columns - An array of form user data.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.

			Returns:
				The id of the new entry in the database.
		*/

		function createEntry($columns, $many_to_many = array(), $tags = array()) {
			// Clean up data
			$insert_values = Link::encodeArray(SQL::prepareData($this->Table, $columns));

			// Insert, if there's a failure return false instead of doing the rest
			$id = SQL::insert($this->Table, $insert_values);
			if (!$id) {
				return false;
			}

			// Handle Many to Many and tags
			$this->handleManyToMany($id, $many_to_many);
			$this->handleTags($id, $tags);

			// Cache and track
			ModuleView::cacheForAll($id, $this->Table);
			AuditTrail::track($this->Table, $id, "created");

			return $id;
		}

		/*
			Function: createChangeRequest
				Creates a change request for an entry and caches it.

			Parameters:
				id - The ID of the entry (or the ID of a pending entry with "p" before the ID).
				changes - The change request data.
				many_to_many - The many to many changes.
				tags - The tag changes.

			Returns:
				The id of the pending change.
		*/

		function createChangeRequest($id, $changes, $many_to_many = array(), $tags = array()) {
			$hook = !empty($this->Hooks["publish"]) ? $this->Hooks["publish"] : false;

			// Allow for "p#" IDs to reference pending table
			if (substr($id, 0, 1) == "p") {
				$existing = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes WHERE id = ?", substr($id, 0, 1));
			} else {
				$existing = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?", $this->Table, $id);
			}

			if ($existing) {
				$change = new PendingChange($existing);
				$change->PublishHook = $hook;
				$change->update($changes, $many_to_many, $tags);
			} else {
				$change = PendingChange::create($this->Table, $id, $changes, $many_to_many, $tags, $this->Module, $hook);
			}

			return $existing ?: $change->ID;
		}

		/*
			Function: createPendingEntry
				Creates an entry in the bigtree_pending_changes table for this form.

			Parameters:
				columns - An array of form user data.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.
				embedded_form - If this is being called from an embedded form, set the user to NULL (defaults to false)

			Returns:
				The id of the new entry in the bigtree_pending_changes table.
		*/

		function createPendingEntry($columns, $many_to_many = array(), $tags = array(), $embedded_form = false) {
			$hook = !empty($this->Hooks["publish"]) ? $this->Hooks["publish"] : false;
			$change = PendingChange::create($this->Table, false, $columns, $many_to_many, $tags, $this->Module, $hook, $embedded_form);

			return $change->ID;
		}

		/*
			Function: deleteEntry
				Deletes an entry from the form and removes any pending changes, then uncaches it from its views.

			Parameters:
				id - The id of the entry.
		*/

		function deleteEntry($id) {
			SQL::delete($this->Table, $id);
			SQL::delete("bigtree_pending_changes", array("table" => $this->Table, "item_id" => $id));

			ModuleView::uncacheForAll($id, $this->Table);
			AuditTrail::track($this->Table, $id, "deleted");
		}

		/*
			Function: deletePendingEntry
				Deletes a pending entry from bigtree_pending_changes and uncaches it.

			Parameters:
				id - The id of the pending entry.
		*/

		function deletePendingEntry($id) {
			SQL::delete("bigtree_pending_changes", $id);

			ModuleView::uncacheForAll("p$id", $this->Table);
			AuditTrail::track($this->Table, "p$id", "deleted-pending");
		}

		/*
		    Function: getArray
				Returns an array of form information.

			Returns:
				Array
		*/

		function getArray() {
			// For backwards compatibility with older data
			$fields = array();
			if (is_array($this->Fields)) {
				foreach ($this->Fields as $field) {
					$fields[$field["column"]] = $field;
				}
			}

			// Old table format
			return array(
				"id" => $this->ID,
				"module" => $this->Module,
				"title" => $this->Title,
				"table" => $this->Table,
				"fields" => $fields,
				"default_position" => $this->DefaultPosition,
				"return_view" => $this->ReturnView,
				"return_url" => $this->ReturnURL,
				"tagging" => $this->Tagging,
				"hooks" => $this->Hooks
			);
		}

		/*
			Function: getEntry
				Returns an entry from the form with all its related information.
				If a pending ID is passed in (prefixed with a p) getPendingEntry is called instead.

			Parameters:
				id - The id of the entry.

			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"tags" - A list of tags for the entry.

				Returns false if the entry could not be found.
		*/

		function getEntry($id) {
			// The entry is pending if there's a "p" prefix on the id
			if (substr($id, 0, 1) == "p") {
				return $this->getPendingEntry($id);
			}

			// Otherwise it's a live entry
			$item = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE id = ?", $id);
			if (!is_array($item)) {
				return false;
			}

			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				$array_val = @json_decode($val, true);

				if (is_array($array_val)) {
					$item[$key] = Link::decodeArray($array_val);
				} else {
					$item[$key] = Link::decode($val);
				}
			}

			// Return the data!
			return array(
				"item" => $item,
				"tags" => Tag::allForEntry($this->Table, $id)
			);
		}

		/*
			Function: getPendingEntry
				Gets a pending entry from the form with all its related information and pending changes applied.

			Parameters:
				id - The id of the entry.

			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"mtm" - A list of many to many pending changes.
				"tags" - A list of tags for the entry.
				"status" - Whether the item is pending ("pending"), published ("published"), or has changes ("updated") awaiting publish.

				Returns false if the entry could not be found.
		*/

		function getPendingEntry($id) {
			$status = "published";
			$many_to_many = array();
			$owner = false;

			// The entry is pending if there's a "p" prefix on the id
			if (substr($id, 0, 1) == "p") {
				$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($id, 1));

				if (!$change) {
					return false;
				}

				$item = json_decode($change["changes"], true);
				$many_to_many = json_decode($change["mtm_changes"], true);
				$temp_tags = json_decode($change["tags_changes"], true);

				// If we have temporary tag IDs, get the full list
				if (array_filter((array) $temp_tags)) {
					// Add the query
					array_unshift($temp_tags, "SELECT * FROM bigtree_tags 
											   WHERE ".implode(" OR ", array_fill(0, count($temp_tags), "id = ?")));
					$tags = call_user_func_array("BigTree\\SQL::fetchAll", $temp_tags);
				} else {
					$tags = array();
				}

				$status = "pending";
				$owner = $change["user"];

			} else {
				// Otherwise it's a live entry
				$item = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE id = ?", $id);

				if (!$item) {
					return false;
				}

				// Apply changes that are pending
				$change = SQL::fetch("SELECT * FROM bigtree_pending_changes
									  WHERE `table` = ? AND `item_id` = ?", $this->Table, $id);
				if ($change) {
					$status = "updated";

					// Apply changes back
					$changes = json_decode($change["changes"], true);
					foreach ($changes as $key => $val) {
						$item[$key] = $val;
					}

					$many_to_many = json_decode($change["mtm_changes"], true);
					$temp_tags = json_decode($change["tags_changes"], true);

					// If we have temporary tag IDs, get the full list
					if (array_filter((array) $temp_tags)) {
						// Add the query
						array_unshift($temp_tags, "SELECT * FROM bigtree_tags 
												   WHERE ".implode(" OR ", array_fill(0, count($temp_tags), "id = ?")));
						$tags = call_user_func_array("BigTree\\SQL::fetchAll", $temp_tags);
					} else {
						$tags = array();
					}

					// If there's no pending changes, just pull the tags
				} else {
					$tags = Tag::allForEntry($this->Table, $id);
				}
			}

			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				$array_val = @json_decode($val, true);
				$item[$key] = is_array($array_val) ? $array_val : $val;
			}

			$item = Link::decodeArray($item);

			return array(
				"item" => $item,
				"mtm" => $many_to_many,
				"tags" => $tags,
				"status" => $status,
				"owner" => $owner
			);
		}

		/*
			Function: getRelatedModuleView
				Returns the view for the same table as this form.

			Returns:
				A ModuleView object or false.
		*/

		function getRelatedModuleView() {
			// Try to find a view that's relating back to this form first
			$form = SQL::escape($this->ID);
			$view = SQL::fetch("SELECT * FROM bigtree_module_interfaces
							    WHERE `settings` LIKE '%\"related_form\":\"$form\"%'
								   OR `settings` LIKE '%\"related_form\": \"$form\"%'");

			// Fall back to any view that uses the same table
			if (!$view) {
				$view = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $this->Table);
			}

			return $view ? new ModuleView($view) : false;
		}

		/*
		    Function: handleManyToMany
				Assigns Many to Many field relationships for an entry.

			Parameters:
				id - The ID of the entry that's being related.
				many_to_many - An array of many to many relationships.

		*/

		function handleManyToMany($id, $many_to_many) {
			if (array_filter((array) $many_to_many)) {
				foreach ($many_to_many as $mtm) {
					// Delete existing
					SQL::delete($mtm["table"], array($mtm["my-id"] => $id));

					if (is_array($mtm["data"])) {
						// Describe table to see if it's positioned
						$table_description = SQL::describeTable($mtm["table"]);

						$position = count($mtm["data"]);
						foreach ($mtm["data"] as $item) {
							$mtm_insert_data = array(
								$mtm["my-id"] => $id,
								$mtm["other-id"] => $item
							);

							// If we're using a positioned table, add it while decreasing the position value
							if (isset($table_description["columns"]["position"])) {
								$mtm_insert_data["position"] = $position--;
							}

							SQL::insert($mtm["table"], $mtm_insert_data);
						}
					}
				}
			}
		}

		/*
		    Function: handleTags
				Assigns tags to an entry.

			Parameters:
				id - The ID of the entry that's being related.
				tags - An array of tags to relate.
		*/

		function handleTags($id, $tags) {
			SQL::delete("bigtree_tags_rel", array("table" => $this->Table, "entry" => $id));

			if (array_filter((array) $tags)) {
				foreach ($tags as $tag) {
					SQL::insert("bigtree_tags_rel", array(
						"table" => $this->Table,
						"entry" => $id,
						"tag" => $tag
					));
				}
			}
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Clean up fields in case old format was used
			foreach ($this->Fields as $key => $field) {
				$field["options"] = is_array($field["options"]) ? $field["options"] : json_decode($field["options"], true);
				$field["column"] = $key;
				$field["title"] = Text::htmlEncode($field["title"]);
				$field["subtitle"] = Text::htmlEncode($field["subtitle"]);
				$this->Fields[$key] = $field;
			}

			$this->Interface->Settings = array(
				"fields" => array_filter((array) $this->Fields),
				"default_position" => $this->DefaultPosition,
				"return_view" => intval($this->ReturnView) ?: null,
				"return_url" => $this->ReturnURL,
				"tagging" => $this->Tagging ? "on" : "",
				"hooks" => array_filter((array) $this->Hooks)
			);
			$this->Interface->Table = $this->Table;
			$this->Interface->Title = $this->Title;

			$this->Interface->save();
		}

		/*
			Function: update
				Updates the form properties and saves them back to the database.
				Also updates the related module action's title and related view columns' numeric status.

			Parameters:
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				return_view - The view to return to when the form is completed.
				return_url - The alternative URL to return to when the form is completed.
				tagging - Whether or not to enable tagging.
		*/

		function update($title, $table, $fields, $hooks = array(), $default_position = "", $return_view = false, $return_url = "", $tagging = "") {
			$this->DefaultPosition = $default_position;
			$this->Fields = $fields;
			$this->Hooks = $hooks;
			$this->ReturnURL = $return_url;
			$this->ReturnView = $return_view;
			$this->Table = $table;
			$this->Tagging = $tagging;
			$this->Title = $title;

			$this->save();

			// Update action titles
			$title = SQL::escape(Text::htmlEncode($title));
			SQL::query("UPDATE bigtree_module_actions SET name = 'Add $title' 
						WHERE interface = ? AND route LIKE 'add%'", $this->ID);
			SQL::query("UPDATE bigtree_module_actions SET name = 'Edit $title' 
						WHERE interface = ? AND route LIKE 'edit%'", $this->ID);

			// Get related views for this table and update numeric status
			$view_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($view_ids as $view_id) {
				$view = new ModuleView($view_id);
				$view->refreshNumericColumns();
			}
		}
		
		/*
			Function: updateEntry
				Updates an entry and caches it.
			
			Parameters:
				id - The id of the entry.
				columns - The columns to update for the entry.
				many_to_many - Many To Many information
				tags - Tag information.
		*/
		
		function updateEntry($id, $columns, $many_to_many = array(), $tags = array()) {
			// Prepare update dat
			$update_columns = Link::encodeArray(SQL::prepareData($this->Table, $columns));
			
			// Do the update
			SQL::update($this->Table, $id, $update_columns);

			// Handle Many to Many and tags
			$this->handleManyToMany($id, $many_to_many);
			$this->handleTags($id, $tags);
			
			// Clear out any pending changes.
			SQL::delete("bigtree_pending_changes", array("item_id" => $id, "table" => $this->Table));

			// Cache and track
			ModuleView::cacheForAll($id, $this->Table);
			AuditTrail::track($this->Table, $id, "updated");
		}

		/*
			Function: updatePendingEntryField
				Update a pending entry's field with a given value.

			Parameters:
				id - The id of the entry.
				field - The field to change.
				value - The value to set.
		*/

		static function updatePendingEntryField($id, $field, $value) {
			$changes = json_decode(SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $id), true);

			if (is_array($value)) {
				$value = Link::encodeArray($value);
			} else {
				$value = Link::encode($value);
			}
			
			$changes[$field] = $value;

			SQL::update("bigtree_pending_changes", $id, array("changes" => $changes));
		}

	}
