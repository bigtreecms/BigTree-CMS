<?php
	/*
		Class: BigTree\ModuleForm
			Provides an interface for handling BigTree module forms.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleForm extends ModuleInterface {

		protected $ID;
		protected $InterfaceSettings;

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
				$interface = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
			}

			// Bad data set
			if (!is_array($interface)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $interface["id"];
				$this->InterfaceSettings = (array) @json_decode($interface["settings"],true);

				$this->DefaultPosition = $this->InterfaceSettings["default_position"];
				$this->Fields = $this->InterfaceSettings["fields"];
				$this->Hooks = array_filter((array) $this->InterfaceSettings["hooks"]);
				$this->Module = $interface["module"];
				$this->ReturnURL = $this->InterfaceSettings["return_url"];
				$this->ReturnView = $this->InterfaceSettings["return_view"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Tagging = $this->InterfaceSettings["tagging"] ? true : false;
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

		function create($module,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "") {
			// Clean up fields for backwards compatibility
			foreach ($fields as $key => $data) {
				$field = array(
					"column" => $data["column"] ? $data["column"] : $key,
					"type" => BigTree::safeEncode($data["type"]),
					"title" => BigTree::safeEncode($data["title"]),
					"subtitle" => BigTree::safeEncode($data["subtitle"]),
					"options" => is_array($data["options"]) ? $data["options"] : array_filter((array)json_decode($data["options"],true))
				);

				// Backwards compatibility with BigTree 4.1 package imports
				foreach ($data as $key => $value) {
					if (!in_array($key,array("title","subtitle","type","options"))) {
						$field["options"][$key] = $value;
					}
				}

				$fields[$key] = $field;
			}

			// Create the parent interface
			$interface = parent::create("form",$module,$title,$table,array(
				"fields" => $clean_fields,
				"default_position" => $default_position,
				"return_view" => $return_view,
				"return_url" => $return_url ? $this->makeIPL($return_url) : "",
				"tagging" => $tagging ? "on" : "",
				"hooks" => is_string($hooks) ? json_decode($hooks,true) : $hooks
			));

			// Get related views for this table and update numeric status
			$view_ids = BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_interfaces WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($view_ids as $view_id) {
				$view = new ModuleView($view_id);
				$view->refreshNumericColumns();
			}

			return new ModuleForm($interface->Array);
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Clean up fields in case old format was used
			foreach ($this->Fields as $key => $field) {
				$field["options"] = is_array($field["options"]) ? $field["options"] : json_decode($field["options"],true);
				$field["column"] = $key;
				$field["title"] = BigTree::safeEncode($field["title"]);
				$field["subtitle"] = BigTree::safeEncode($field["subtitle"]);
				$this->Fields[$key] = $field;
			}

			// Update the DB
			BigTreeCMS::$DB->update("bigtree_module_interfaces",$this->ID,array(
				"module" => $this->Module,
				"title" => BigTree::safeEncode($this->Title),
				"table" => $this->Table,
				"settings" => array(
					"fields" => array_filter((array) $this->Fields),
					"default_position" => $this->DefaultPosition,
					"return_view" => intval($this->ReturnView) ?: null,
					"return_url" => $this->ReturnURL,
					"tagging" => $this->Tagging ? "on" : "",
					"hooks" => array_filter((array) $this->Hooks)
				)
			));

			AuditTrail::track("bigtree_module_interfaces",$this->ID,"updated");
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

		function update($id,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "") {
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
			$title = BigTreeCMS::$DB->escape(BigTree::safeEncode($title));
			BigTreeCMS::$DB->query("UPDATE bigtree_module_actions SET name = 'Add $title' 
									WHERE interface = ? AND route LIKE 'add%'", $this->ID);
			BigTreeCMS::$DB->query("UPDATE bigtree_module_actions SET name = 'Edit $title' 
									WHERE interface = ? AND route LIKE 'edit%'", $this->ID);

			// Get related views for this table and update numeric status
			$view_ids = BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_interfaces WHERE `type` = 'view' AND `table` = ?", $table);
			foreach ($view_ids as $view_id) {
				$view = new ModuleView($view_id);
				$view->refreshNumericColumns();
			}
		}

	}
