<?php
	/*
		Class: BigTree\ModuleView
			Provides an interface for handling BigTree module views.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleView extends ModuleInterface {

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
				$interface = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $interface);
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
				"description" => BigTree::safeEncode($description),
				"type" => $type,
				"fields" => $fields,
				"options" => $settings,
				"actions" => $actions,
				"preview_url" => $preview_url ? $this->makeIPL($preview_url) : "",
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
			Function: refreshNumericColumns
				Updates the view's columns to designate whether they are numeric or not based on parsers, column type, and related forms.
		*/

		function refreshNumericColumns($id) {
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
				$table = BigTree::describeTable($this->Table);

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
				"description" => BigTree::safeEncode($this->Description),
				"type" => $this->Type,
				"fields" => array_filter((array) $this->Fields),
				"options" => (array) $this->Settings,
				"actions" => array_filter((array) $this->Actions),
				"preview_url" => $preview_url ? Link::encode($preview_url) : "",
				"related_form" => $related_form ? intval($related_form) : null
			);

			parent::save();
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
			$action = BigTree\ModuleAction::getByInterface($id);
			$action->Name = "View ".BigTree::safeEncode($title);
			$action->save();
		}

	}
