<?php
	/*
		Class: BigTree\PageRevision
			Provides an interface for handling BigTree page revisions.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class PageRevision {

		static $Table = "bigtree_page_revisions";

		protected $ID;
		protected $Author;
		protected $Page;
		protected $UpdatedAt;

		public $External;
		public $MetaDescription;
		public $NewWindow;
		public $Resources;
		public $Saved;
		public $SavedDescription;
		public $Template;
		public $Title;

		/*
			Constructor:
				Builds a PageRevision object referencing an existing database entry.

			Parameters:
				revision - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($revision) {
			// Passing in just an ID
			if (!is_array($revision)) {
				$revision = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources WHERE id = ?", $revision);
			}

			// Bad data set
			if (!is_array($revision)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $revision["id"];
				$this->Page = $revision["page"];
				$this->UpdatedAt = $revision["updated_at"];

				// Get user information -- allByPage provides this already
				if (!$revision["name"] || !$revision["email"]) {
					$this->Author = BigTreeCMS::$DB->fetch("SELECT id, name, email FROM bigtree_users WHERE id = ?", $revision["author"]);
				} else {
					$this->Author = array("id" => $revision["author"], "name" => $revision["name"], "email" => $revision["email"]);
				}

				$this->External = $revision["external"] ? Link::decode($revision["external"]) : "";
				$this->MetaDescription = $revision["meta_description"];
				$this->NewWindow = $revision["new_window"] ? true : false;
				$this->Resources = array_filter((array) @json_decode($revision["resources"],true));
				$this->Saved = $revision["saved"] ? true : false;
				$this->SavedDescription = $revision["saved_description"];
				$this->Template = $revision["template"];
				$this->Title = $revision["title"];
			}
		}

		/*
			Function: allByPage
				Get a list of revisions for a given page.

			Parameters:
				page - A page ID to pull revisions for.
				sort - Sort by (defaults to "updated_at DESC")
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of "saved" revisions and "unsaved" revisions.
		*/

		function allByPage($page,$sort = "updated_at DESC",$return_arrays = false) {
			$saved = $unsaved = array();
			$revisions = BigTreeCMS::$DB->fetchAll("SELECT bigtree_users.name, 
														   bigtree_users.email, 
														   bigtree_page_revisions.saved, 
														   bigtree_page_revisions.saved_description, 
														   bigtree_page_revisions.updated_at, 
														   bigtree_page_revisions.id 
													FROM bigtree_page_revisions JOIN bigtree_users 
													ON bigtree_page_revisions.author = bigtree_users.id 
													WHERE page = ? 
													ORDER BY updated_at DESC", $page);

			foreach ($revisions as $revision) {
				if ($revision["saved"]) {
					$saved[] = $return_arrays ? $revision : new PageRevision($revision);
				} else {
					$unsaved[] = $return_arrays ? $revision : new PageRevision($revision);
				}
			}

			return array("saved" => $saved, "unsaved" => $unsaved);
		}

		/*
			Function: create
				Saves a Page object's data as a revision.

			Parameters:
				page - A Page object.
				description - The revision description (to save permanantly)

			Returns:
				A PageRevision object.
		*/

		function create(Page $page,$description = "") {
			$id = BigTreeCMS::$DB->insert("bigtree_page_revisions",array(
				"page" => $page->ID,
				"title" => $page->Title,
				"meta_description" => $page->MetaDescription,
				"template" => $page->Template,
				"external" => $page->External ? Link::encode($page->External) : "",
				"new_window" => $page->NewWindow ? "on" : "",
				"resources" => $page->Resources,
				"author" => $page->LastEditedBy,
				"updated_at" => $page->UpdatedAt,
				"saved" => $description ? "on" : "",
				"saved_description" => $description
			));

			AuditTrail::("bigtree_page_revisions",$id,"created");
			
			return new PageRevision($id);
		}

		/*
			Function: save
				Saves object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_page_revisions",$this->ID,array(
				"external" => $this->External,
				"new_window" => $this->NewWindow ? "on" : "",
				"resources" => $this->Resources,
				"saved" => $this->Saved ? "on" : "",
				"saved_description" => BigTree::safeEncode($this->SavedDescription),
				"template" => $this->Template,
				"title" => $this->Title
			));

			AuditTrail::track("bigtree_page_revisions",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the page revision to save it as a favorite.

			Parameters:
				description - Saved description.
		*/

		function update($description) {
			$this->Saved = true;
			$this->SavedDescription = $description;
			$this->save();
		}

	}
