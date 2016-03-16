<?php
	/*
		Class: BigTree\PendingChange
			Provides an interface for handling BigTree pending changes.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class PendingChange extends BaseObject {

		static $Table = "bigtree_pending_changes";

		protected $Date;
		protected $ID;

		public $Changes;
		public $ItemID;
		public $ManyToManyChanges;
		public $Module;
		public $PendingPageParent;
		public $PublishHook;
		public $Table;
		public $TagsChanges;
		public $Title;
		public $User;

		/*
			Constructor:
				Builds a PendingChange object referencing an existing database entry.

			Parameters:
				change - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($change) {
			// Passing in just an ID
			if (!is_array($change)) {
				$change = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change);
			}

			// Bad data set
			if (!is_array($change)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->Date = $change["date"];
				$this->ID = $change["id"];

				$this->Changes = (array) @json_decode($change["changes"],true);
				$this->ItemID = ($change["item_id"] !== null) ? $change["item_id"] : null;
				$this->ManyToManyChanges = (array) @json_decode($change["mtm_changes"],true);
				$this->Module = $change["module"];
				$this->PendingPageParent = $change["pending_page_parent"];
				$this->PublishHook = $change["publish_hook"];
				$this->Table = $change["table"];
				$this->TagsChanges = (array) @json_decode($change["tags_changes"],true);
				$this->Title = $change["title"];
				$this->User = $change["user"];
			}
		}

		// $this->UserAccessLevel
		function _getUserAccessLevel() {

		}

	}