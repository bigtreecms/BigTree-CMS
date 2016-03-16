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
	}
