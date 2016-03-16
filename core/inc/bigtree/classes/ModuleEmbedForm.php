<?php
	/*
		Class: BigTree\ModuleEmbedForm
			Provides an interface for handling BigTree module embeddable forms.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleEmbedForm extends ModuleInterface {

		protected $Hash;
		protected $ID;
		protected $InterfaceSettings;

		public $CSS;
		public $DefaultPending;
		public $DefaultPosition;
		public $Fields;
		public $Hooks;
		public $Module;
		public $RedirectURL;
		public $Tagging;
		public $Title;

		/*
			Constructor:
				Builds a ModuleEmbedForm object referencing an existing database entry.

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
				$this->Hash = $this->InterfaceSettings["hash"];

				$this->CSS = $this->InterfaceSettings["css"];
				$this->DefaultPending = $this->InterfaceSettings["default_pending"] ? true : false;
				$this->DefaultPosition = $this->InterfaceSettings["default_position"];
				$this->Fields = $this->InterfaceSettings["fields"];
				$this->Hooks = array_filter((array) $this->InterfaceSettings["hooks"]);
				$this->Module = $interface["module"];
				$this->RedirectURL = $this->InterfaceSettings["redirect_url"];
				$this->Table = $interface["table"]; // We can't declare this publicly because it's static for the BaseObject class
				$this->Title = $interface["title"];
			}
		}
	}
	