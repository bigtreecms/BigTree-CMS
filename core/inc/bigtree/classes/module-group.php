<?php
	/*
		Class: BigTree\ModuleGroup
			Provides an interface for handling BigTree module groups.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class ModuleGroup extends BaseObject {

		static $Table = "bigtree_module_groups";

		protected $ID;

		public $Icon;
		public $InNav;
		public $Interface;
		public $Level;
		public $Module;
		public $Name;
		public $Position;
		public $Route;


		/*
			Constructor:
				Builds a ModuleGroup object referencing an existing database entry.

			Parameters:
				group - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($group) {
			// Passing in just an ID
			if (!is_array($group)) {
				$group = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_module_actions WHERE id = ?", $group);
			}

			// Bad data set
			if (!is_array($group)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $group["id"];

				$this->Name = $group["name"];
				$this->Position = $group["position"];
				$this->Route = $group["route"];
			}
		}