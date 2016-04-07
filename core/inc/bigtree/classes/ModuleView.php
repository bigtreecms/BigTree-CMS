<?php
	/*
		Class: BigTree\ModuleView
			Provides an interface for handling BigTree module views.
	*/

	namespace BigTree;

	class ModuleView extends ModuleInterface {

		static $CoreTypes = array(
			"searchable" => "Searchable List",
			"draggable" => "Draggable List",
			"nested" => "Nested Draggable List",
			"grouped" => "Grouped List",
			"images" => "Image List",
			"images-grouped" => "Grouped Image List"
		);
		static $Plugins = array();
		
		protected $ID;
		protected $InterfaceSettings;

		public $Actions;
		public $Description;
		public $Fields;
		public $Module;
		public $PreviewURL;
		public $RelatedForm;
		public $Settings;
		public $Title;
		public $Type;

		/*
			Constructor:
				Builds a ModuleView object referencing an existing database entry.

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
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $interface["id"];
				$this->InterfaceSettings = (array) @json_decode($interface["settings"],true);

				$this->Actions = $this->InterfaceSettings["actions"];
				$this->Description = $this->InterfaceSettings["description"];
				$this->Fields = $this->InterfaceSettings["fields"];
				$this->Module = $interface["module"];
				$this->PreviewURL = $this->InterfaceSettings["preview_url"];
				$this->RelatedForm = $this->InterfaceSettings["related_form"];
				$this->Settings = $this->InterfaceSettings["options"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Title = $interface["title"];
				$this->Type = $this->InterfaceSettings["type"];
			}
		}

		/*
			Function: cache
				Caches a single item in the view to the bigtree_module_view_cache table.
				Private method used by cacheAllData and cacheForAll
		*/

		private function cache($item,$parsers,$poplists,$original_item,$group_based_permissions) {
			// If we have a filter function, ask it first if we should cache it
			if (!empty($this->Settings["filter"])) {
				if (!call_user_func($this->Settings["filter"],$item)) {
					return false;
				}
			}

			// Stringify any columns that happen to be arrays (potentially from a pending record)
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = json_encode($val);
				}
			}

			// Setup the fields and VALUES to INSERT INTO the cache table.
			$status = "l";
			$pending_owner = 0;
			if ($item["bigtree_changes"]) {
				$status = "c";
			} elseif (isset($item["bigtree_pending"])) {
				$status = "p";
				$pending_owner = $item["bigtree_pending_owner"];
			}

			// Setup our array of insert values with what we know already
			$insert_values = array(
				"view" => $this->ID,
				"id" => $item["id"],
				"status" => $status,
				"position" => isset($item["position"]) ? $item["position"] : 0,
				"approved" => isset($item["approved"]) ? $item["approved"] : "",
				"archived" => isset($item["archived"]) ? $item["archived"] : "",
				"featured" => isset($item["featured"]) ? $item["featured"] : "",
				"pending_owner" => $pending_owner
			);

			// Figure out which column we're going to use to sort the view.
			if ($this->Settings["sort"]) {
				$sort_field = SQL::nextColumnDefinition(ltrim($this->Settings["sort"],"`"));
			} else {
				$sort_field = false;
			}

			// Let's see if we have a grouping field.  If we do, let's get all that info and cache it as well.
			if (isset($this->Settings["group_field"]) && $this->Settings["group_field"]) {
				$value = $item[$this->Settings["group_field"]];

				// Check for a parser
				if (isset($this->Settings["group_parser"]) && $this->Settings["group_parser"]) {
					$value = BigTree::runParser($item,$value,$this->Settings["group_parser"]);
				}

				// Add the group field
				$insert_values["group_field"] = $value;

				// If there's a sort field for the group, add it
				if (is_numeric($value) && $this->Settings["other_table"] && $this->Settings["ot_sort_field"]) {
					$sort_field_value = SQL::fetchSingle("SELECT `".$this->Settings["ot_sort_field"]."` FROM `".$this->Settings["other_table"]."` WHERE id = ?", $value);
					$insert_values["group_sort_field"] = $sort_field_value;
				}
			}

			// Check for a nesting column
			if (!empty($this->Settings["nesting_column"])) {
				$insert_values["group_field"] = $item[$this->Settings["nesting_column"]];
			}

			// Group based permissions data
			if (!empty($group_based_permissions["enabled"]) && $group_based_permissions["table"] == $this->Table) {
				$insert_values["gbp_field"] = $item[$group_based_permissions["group_field"]];
				$insert_values["published_gbp_field"] = $original_item[$group_based_permissions["group_field"]];
			}

			// Run parsers
			foreach ($parsers as $key => $parser) {
				$item[$key] = BigTree::runParser($item,$item[$key],$parser);
			}

			// Run database populated list hooks
			foreach ($poplists as $key => $pop) {
				$pop_description = SQL::fetchSingle("SELECT `".$pop["description"]."` FROM `".$pop["table"]."` WHERE id = ?", $item[$key]);
				if ($pop_description !== false) {
					$item[$key] = $pop_description;
				}
			}

			// Insert into the view cache
			if ($this->Type == "images" || $this->Type == "images-grouped") {
				$insert_values["column1"] = $item[$this->Settings["image"]];
			} else {
				$x = 1;
				foreach ($this->Fields as $field => $options) {
					$item[$field] = Link::decode($item[$field]);
					$insert_values["column$x"] = Text::htmlEncode(strip_tags($item[$field]));
					$x++;
				}
			}

			if ($sort_field && $item[$sort_field]) {
				$insert_values["sort_field"] = $item[$sort_field];
			}

			SQL::insert("bigtree_module_view_cache",$insert_values);

			return true;
		}

		/*
			Function: cacheAllData
				Grabs all the data from the view and does parsing on it based on automatic assumptions and manual parsers.
		*/
		
		function cacheAllData() {
			// See if we already have cached data.
			if (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_view_cache WHERE view = ?", $this->ID)) {
				return false;
			}
			
			// Find out what module we're using so we can get the gbp_field
			$gbp = SQL::fetchSingle("SELECT gbp FROM bigtree_modules WHERE id = ?", $this->Module);
			$group_based_permissions = json_decode($gbp,true);
			
			// Setup information on our parsers and populated lists.
			$form = $this->RelatedModuleForm;
			$parsers = array();
			$poplists = array();
			
			foreach ($this->Fields as $key => $field) {
				// Get the form field
				$form_field = isset($form->Fields[$key]) ? $form->Fields[$key] : false;
				
				if ($field["parser"]) {
					$parsers[$key] = $field["parser"];
				} elseif ($form_field && $form_field["type"] == "list" && $form_field["options"]["list_type"] == "db") {
					$poplists[$key] = array(
						"description" => $form_field["options"]["pop-description"],
						"table" => $form_field["options"]["pop-table"]
					);
				}
			}
			
			// See if we need to modify the cache table to add more fields.
			$field_count = count($this->Fields);
			$table_description = SQL::describeTable("bigtree_module_view_cache");
			$column_count = count($table_description["columns"]) - 13;
			
			while ($field_count > $column_count) {
				$column_count++;
				SQL::query("ALTER TABLE bigtree_module_view_cache ADD COLUMN column$column_count TEXT NOT NULL AFTER column".($column_count - 1));
			}
			
			// Cache all records
			$published = SQL::fetchAll("SELECT `".$this->Table."`.*, bigtree_pending_changes.changes AS bigtree_changes 
										FROM `".$this->Table."` LEFT JOIN bigtree_pending_changes 
										ON (bigtree_pending_changes.item_id = `".$this->Table."`.id AND 
											bigtree_pending_changes.table = '".$this->Table."')");
			$pending = SQL::fetchAll("SELECT * FROM bigtree_pending_changes 
									  WHERE `table` = '".$this->Table."' AND item_id IS NULL");

			foreach ($published as $item) {
				$original_item = $item;

				// Apply pending changes to the published entry before caching
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"],true);
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}	

				$this->cache($item,$parsers,$poplists,$original_item,$group_based_permissions);
			}

			foreach ($pending as $pending_change) {
				$item = json_decode($pending_change["changes"],true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $pending_change["user"];
				$item["id"] = "p".$pending_change["id"];
				
				$this->cache($item,$parsers,$poplists,$item,$group_based_permissions);
			}
			
			return true;
		}

		/*
			Function: cacheForAll
				Caches a new database row for all Module Views that use the same table.

			Parameters:
				id - The id of the row.
				table - The table the row is in.
				pending - Whether this is actually a pending entry (defaults to false)
		*/

		static function cacheForAll($id, $table, $pending = false) {
			if (!$pending) {
				$item = SQL::fetch("SELECT `$table`.*, bigtree_pending_changes.changes AS bigtree_changes 
									FROM `$table` LEFT JOIN bigtree_pending_changes 
									ON (bigtree_pending_changes.item_id = `$table`.id AND 
										bigtree_pending_changes.table = '$table') 
									WHERE `$table`.id = ?", $id);

				$original_item = $item;

				// Apply changes overtop existing values
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"],true);
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}
			} else {
				$pending_item = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

				$item = json_decode($pending_item["changes"],true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $pending_item["user"];
				$item["id"] = "p".$pending_item["id"];

				$original_item = $item;
			}

			$interface_ids = SQL::fetchAllSingle("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'view' AND `table` = ?", $table);

			foreach ($interface_ids as $interface) {
				$view = new ModuleView($interface);

				// Delete any existing cache data on this row
				SQL::delete("bigtree_module_view_cache",array("view" => $view->ID, "id" => $item["id"]));

				// In case this view has never been cached, run the whole view, otherwise just this one.
				if (!$view->cacheAllData()) {

					// Find out what module we're using so we can get the gbp_field
					$group_based_permissions = SQL::fetchSingle("SELECT gbp FROM bigtree_modules WHERE id = ?", $view->Module);

					$form = $view->RelatedModuleForm;
					$parsers = $poplists = array();

					foreach ($view->Fields as $key => $field) {
						$form_field = !empty($form["fields"][$key]) ? $form["fields"][$key] : false;

						if ($field["parser"]) {
							$parsers[$key] = $field["parser"];
						} elseif ($form_field && $form_field["type"] == "list" && $form_field["options"]["list_type"] == "db") {
							$poplists[$key] = array(
								"description" => $form_field["options"]["pop-description"],
								"table" => $form_field["options"]["pop-table"]
							);
						}
					}

					$view->cache($item,$parsers,$poplists,$original_item,$group_based_permissions);
				}
			}
		}

		/*
			Function: clearCache
				Clears the cache of the view.
		*/

		function clearCache() {
			SQL::delete("bigtree_module_view_cache", array("view" => $this->ID));
		}

		/*
			Function: clearCacheForTable
				Clears all module view caches with the given table name.

			Parameters:
				table - A table to reset caches for.
		*/

		static function clearCacheForTable($table) {
			$interface_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_module_interfaces
												  WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($interface_ids as $id) {
				SQL::delete("bigtree_module_view_cache", array("view" => $id));
			}
		}

		/*
			Function: create
				Creates a module view.

			Parameters:
				module - The module ID that this view relates to.
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				A ModuleView object.
		*/

		static function create($module,$title,$description,$table,$type,$settings,$fields,$actions,$related_form,$preview_url = "") {
			$interface = parent::create("view",$module,$title,$table,array(
				"description" => Text::htmlEncode($description),
				"type" => $type,
				"fields" => $fields,
				"options" => $settings,
				"actions" => $actions,
				"preview_url" => $preview_url ? Link::encode($preview_url) : "",
				"related_form" => $related_form ? intval($related_form) : null
			));

			$view = new ModuleView($interface->Array);
			$view->refreshNumericColumns();

			return $view;
		}

		/*
			Function: generateActionClass
				Returns the button class for the given action and item.

			Parameters:
				action - The action route for the item (edit, feature, approve, etc)
				item - The entry to check the action for.

			Returns:
				Class name for the <a> tag.

				For example, if the item is already featured, this returns "icon_featured icon_featured_on" for the "feature" action.
				If the item isn't already featured, it would simply return "icon_featured" for the "feature" action.
		*/

		static function generateActionClass($action,$item) {
			$class = "";

			if (isset($item["bigtree_pending"]) && $action != "edit" && $action != "delete") {
				return "icon_disabled";
			}
			
			if ($action == "feature") {
				$class = "icon_feature";
				if ($item["featured"]) {
					$class .= " icon_feature_on";
				}
			}
			
			if ($action == "edit") {
				$class = "icon_edit";
			}
			
			if ($action == "delete") {
				$class = "icon_delete";
			}
			
			if ($action == "approve") {
				$class = "icon_approve";
				if ($item["approved"]) {
					$class .= " icon_approve_on";
				}
			}
			
			if ($action == "archive") {
				$class = "icon_archive";
				if ($item["archived"]) {
					$class .= " icon_archive_on";
				}
			}
			
			if ($action == "preview") {
				$class = "icon_preview";
			}
			
			return $class;
		}

		/*
			Function: getRelatedModuleForm
				Returns the form for the same table as this view.
			
			Returns:
				A form entry with fields decoded.
		*/

		function getRelatedModuleForm() {
			if ($this->RelatedForm) {
				return new ModuleForm($this->RelatedForm);
			}

			$form = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'form' AND `table` = ?", $this->Table);

			return $form ? new ModuleForm($form) : false;
		}

		/*
			Function: refreshNumericColumns
				Updates the view's columns to designate whether they are numeric or not based on parsers, column type, and related forms.
		*/

		function refreshNumericColumns() {
			if (array_filter((array) $this->Fields)) {
				$numeric_column_types = array(
					"int",
					"float",
					"double",
					"double precision",
					"tinyint",
					"smallint",
					"mediumint",
					"bigint",
					"real",
					"decimal",
					"dec",
					"fixed",
					"numeric"
				);

				$form = BigTreeAutoModule::getRelatedFormForView($this->Array);
				$table = SQL::describeTable($this->Table);

				foreach ($this->Fields as $key => $field) {
					$numeric = false;

					if (in_array($table["columns"][$key]["type"],$numeric_column_types)) {
						$numeric = true;
					}

					if ($field["parser"] || ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["list_type"] == "db")) {
						$numeric = false;
					}

					$this->Fields[$key]["numeric"] = $numeric;
				}

				$this->save();
			}
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			$this->InterfaceSettings = array(
				"description" => Text::htmlEncode($this->Description),
				"type" => $this->Type,
				"fields" => array_filter((array) $this->Fields),
				"options" => (array) $this->Settings,
				"actions" => array_filter((array) $this->Actions),
				"preview_url" => $this->PreviewURL ? Link::encode($this->PreviewURL) : "",
				"related_form" => $this->RelatedForm ? intval($this->RelatedForm) : null
			);

			parent::save();
		}

		/*
			Function: uncacheForAll
				Removes a database row from all Module View caches with the given table.

			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
		*/

		static function uncacheForAll($id, $table) {
			$view_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_module_interfaces 
											 WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($view_ids as $view_id) {
				SQL::delete("bigtree_module_view_cache",array("view" => $view_id, "id" => $id));
			}
		}

		/*
			Function: update
				Updates the module view and the associated module action's title.

			Parameters:
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				options - View options array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.
		*/

		function update($title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url = "") {
			$this->Actions = $actions;
			$this->Description = $description;
			$this->Fields = $fields;
			$this->PreviewURL = $preview_url;
			$this->RelatedForm = $related_form;
			$this->Settings = $options;
			$this->Table = $table;
			$this->Title = $title;
			$this->Type = $type;

			// This method will automatically save
			$this->refreshNumericColumns();

			// Update related action titles
			$action = ModuleAction::getByInterface($this->ID);
			$action->Name = "View ".Text::htmlEncode($title);
			$action->save();
		}

	}
