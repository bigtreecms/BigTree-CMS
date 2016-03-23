<?php
	/*
		Class: BigTree\Redirect
			Provides an interface for handling BigTree 404s and 301s.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Redirect extends BaseObject {

		protected $ID;
		protected $Requests;

		public $BrokenURL;
		public $RedirectURL;
		public $Ignored;

		/*
			Constructor:
				Builds a Redirect object referencing an existing database entry.

			Parameters:
				redirect - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($redirect) {
			// Passing in just an ID
			if (!is_array($redirect)) {
				$redirect = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_404s WHERE id = ?", $redirect);
			}

			// Bad data set
			if (!is_array($redirect)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $redirect["id"];
				$this->Requests = $redirect["requests"];

				$this->BrokenURL = $redirect["broken_url"];
				$this->RedirectURL = BigTree\Link::decode($redirect["redirect_url"]);
				$this->Ignored = $redirect["ignored"] ? true : false;
			}
		}

		/*
			Function: clearEmpty
				Removes all 404s that don't have 301 redirects.
		*/

		static function clearEmpty() {
			BigTreeCMS::$DB->delete("bigtree_404s",array("redirect_url" => ""));
			AuditTrail::track("bigtree_404s","All","Cleared Empty");
		}

		/*
			Function: create
				Creates a 301 redirect.

			Parameters:
				from - The 404 path
				to - The 301 target

			Returns:
				A Redirect object.
		*/

		function create($from,$to) {
			$from = htmlspecialchars(strip_tags(rtrim(str_replace(WWW_ROOT,"",$from),"/")));
			$to = htmlspecialchars(Link::encode($to));

			// See if the from already exists
			$existing = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ?", $from);
			if ($existing) {
				BigTreeCMS::$DB->update("bigtree_404s",$existing["id"],array("redirect_url" => $to));
				AuditTrail::track("bigtree_404s",$existing["id"],"updated");

				return new Redirect($existing["id"]);
			} else {
				$id = BigTreeCMS::$DB->insert("bigtree_404s",array(
					"broken_url" => $from,
					"redirect_url" => $to
				));
				AuditTrail::track("bigtree_404s",$id,"created");

				return new Redirect($id);
			}
		}

		/*
			Function: delete
				Deletes the redirect.
		*/

		function delete() {
			BigTreeCMS::$DB->delete("bigtree_404s",$this->ID);
			AuditTrail::track("bigtree_404s",$this->ID,"deleted");
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Try to convert the short URL into a full one
			$redirect_url = $this->RedirectURL;
			if (strpos($redirect_url,"//") === false) {
				$redirect_url = WWW_ROOT.ltrim($redirect_url,"/");
			}
			$redirect_url = htmlspecialchars(Link::encode($redirect_url));

			// Don't use static roots if they're the same as www just in case they are different when moving environments
			if (WWW_ROOT === STATIC_ROOT) {
				$redirect_url = str_replace("{staticroot}","{wwwroot}",$redirect_url);
			}

			BigTreeCMS::$DB->update("bigtree_404s",$this->ID,array(
				"broken_url" => htmlspecialchars(strip_tags(rtrim(str_replace(WWW_ROOT,"",$this->BrokenURL),"/"))),
				"redirect_url" => $redirect_url,
				"ignored" => $this->Ignored ? "on" : "";
			));

			AuditTrail::track("bigtree_404s",$this->ID,"updated");
		}

	}
