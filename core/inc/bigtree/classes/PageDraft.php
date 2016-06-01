<?php
	/*
		Class: BigTree\PageDraft
			Provides an interface for BigTree page drafts.
	*/

	namespace BigTree;

	class PageDraft extends BaseObject {

		protected $CreatedAt;
		protected $ID;
		protected $LastEditedBy;
		protected $PageID;
		protected $Pending;
		protected $UpdatedAt;

		public $AnalyticsPageViews;
		public $Archived;
		public $ArchivedInherited;
		public $ExpireAt;
		public $External;
		public $InNav;
		public $MaxAge;
		public $MetaDescription;
		public $NavigationTitle;
		public $NewWindow;
		public $Parent;
		public $Path;
		public $Position;
		public $PublishAt;
		public $Resources;
		public $Route;
		public $SEOInvisible;
		public $Template;
		public $Title;
		public $Trunk;

		/*
			Constructor:
				Builds a PageDraft object referencing an existing database entry.

			Parameters:
				change - Either an pending change ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($change = false) {
			// Allow empty instances
			if ($change === false) {
				return;
			}

			// Passing in just an ID
			if (!is_array($change)) {
				$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change);
			}

			// Bad data set
			if (!is_array($change)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$data = json_decode($change["changes"], true);

				// Protected vars first
				$this->CreatedAt = $change["date"];
				$this->ID = $change["id"];
				$this->LastEditedBy = $change["user"];
				$this->Pending = true;
				$this->UpdatedAt = $change["date"];

				// Public vars
				$this->ExpireAt = $data["expire_at"] ?: false;
				$this->External = $data["external"] ? Link::decode($data["external"]) : false;
				$this->InNav = $data["in_nav"] ? true : false;
				$this->MaxAge = $data["max_age"] ?: false;
				$this->MetaDescription = $data["meta_description"];
				$this->NavigationTitle = $data["nav_title"];
				$this->NewWindow = $data["new_window"] ? true : false;
				$this->Parent = $change["pending_page_parent"];
				$this->Path = false;
				$this->PublishAt = $data["publish_at"] ?: false;
				$this->Resources = is_array($data["resources"]) ? $data["resources"] : json_decode($data["resources"], true);
				$this->Route = $data["route"] ?: Link::urlify($data["nav_title"]);
				$this->SEOInvisible = $data["seo_invisible"] ? true : false;
				$this->Template = $data["template"];
				$this->Title = $data["title"];
				$this->Trunk = $data["trunk"] ? true : false;
			}
		}

		/*
			Function: getForPage
				Returns a PageDraft object for the passed in Page object.

			Parameters:
				page - A Page object.

			Returns:
				A PageDraft object.
		*/

		static function getForPage($page) {
			$draft = new PageDraft;
			$properties = get_object_vars($page);
			foreach ($properties as $key => $value) {
				$draft->$key = $value;
			}
			print_r($draft);
		}

	}
