<?php
	/*
		Class: BigTree\ModuleReport
			Provides an interface for handling BigTree module reports.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleReport extends ModuleInterface {

		protected $ID;
		protected $InterfaceSettings;

		public $Fields;
		public $Filters;
		public $Module;
		public $Parser;
		public $Title;
		public $Type;
		public $View;

		/*
			Constructor:
				Builds a ModuleReport object referencing an existing database entry.

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

				$this->Fields = $this->InterfaceSettings["fields"];
				$this->Filters = $this->InterfaceSettings["filters"];
				$this->Module = $interface["module"];
				$this->Parser = $this->InterfaceSettings["parser"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Title = $interface["title"];
				$this->Type = $this->InterfaceSettings["type"];
				$this->View = $this->InterfaceSettings["view"];
			}
		}

		/*
			Function: save
				Saves the object's properties back to the database and updates InterfaceSettings.
		*/

		function save() {
			$this->InterfaceSettings = array(
				"type" => $this->Type,
				"filters" => $this->Filters,
				"fields" => $this->Fields,
				"parser" => $this->Parser,
				"view" => $this->View ?: null
			);

			parent::save();
		}

		/*
			Function: update
				Updates the module report's properties and saves them back to the interface settings and database.

			Parameters:
				title - The title of the report.
				table - The table for the report data.
				type - The type of report (csv or view).
				filters - The filters a user can use to create the report.
				fields - The fields to show in the CSV export (if type = csv).
				parser - An optional parser function to run on the CSV export data (if type = csv).
				view - A module view ID to use (if type = view).
		*/

		function update($title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$this->Fields = $fieldss;
			$this->Filters = $filters;
			$this->Parser = $parser;
			$this->Table = $table;
			$this->Title = $title;
			$this->Type = $type;
			$this->View = $view ?: null;

			$this->save();
		}
	}
