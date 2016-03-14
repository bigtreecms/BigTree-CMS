<?php
	/*
		Class: BigTree\Template
			Provides an interface for handling BigTree templates.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Template extends BaseObject {

		static $Table = "bigtree_templates";

		protected $ID;

		public $Fields;
		public $Level;
		public $Module;
		public $Name;
		public $Position;
		public $Routed;

		/*
			Constructor:
				Builds a Template object referencing an existing database entry.

			Parameters:
				template - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($template) {
			// Passing in just an ID
			if (!is_array($template)) {
				$template = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_templates WHERE id = ?", $template);
			}

			// Bad data set
			if (!is_array($template)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $template["id"];

				$this->Fields = array_filter((array) @json_decode($template["resources"],true));
				$this->Level = $template["level"];
				$this->Module = $template["module"];
				$this->Name = $template["name"];
				$this->Position = $template["position"];
				$this->Routed = $template["route"] ? true : false;
			}
		}

		/*
			Get Magic Method:
				Allows retrieval of the write-protected ID property and other heavy data processing properties.
		*/

		function __get($property) {

			// Read only property
			if ($property == "Date") {
				return $this->Date;
			}

			// Read-only properties that require a lot of work, stored as protected methods
			if ($property == "UserAccessLevel") {
				$this->UserAccessLevel = $this->_getUserAccessLevel();
				return $this->UserAccessLevel;
			}

			return parent::__get($property);
		}

		// $this->UserAccessLevel
		function _getUserAccessLevel() {
			
		}

	}