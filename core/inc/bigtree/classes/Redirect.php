<?php
	/*
		Class: BigTree\Redirect
			Provides an interface for handling BigTree 404s and 301s.
	*/

	namespace BigTree;

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

		function __construct($redirect = null) {
			if ($redirect !== null) {
				// Passing in just an ID
				if (!is_array($redirect)) {
					$redirect = SQL::fetch("SELECT * FROM bigtree_404s WHERE id = ?", $redirect);
				}

				// Bad data set
				if (!is_array($redirect)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $redirect["id"];
					$this->Requests = $redirect["requests"];

					$this->BrokenURL = $redirect["broken_url"];
					$this->RedirectURL = Link::decode($redirect["redirect_url"]);
					$this->Ignored = $redirect["ignored"] ? true : false;
				}
			}
		}

		/*
			Function: catch404
				Manually catch and display the 404 page from a routed template; logs missing page with handle404
		*/
		
		static function catch404() {
			global $admin, $bigtree, $cms, $db;
			
			// Wipe any content that's already been drawn
			ob_clean();

			if (static::handle404(str_ireplace(WWW_ROOT, "", Link::currentURL()))) {
				$bigtree["layout"] = "default";
				include SERVER_ROOT."templates/basic/_404.php";

				// Get content and start the buffer again
				$bigtree["content"] = ob_get_clean();
				ob_start();

				// Draw content in the provided layout
				include "../templates/layouts/".$bigtree["layout"].".php";
				die();
			}
		}

		/*
			Function: clearEmpty
				Removes all 404s that don't have 301 redirects.
		*/

		static function clearEmpty() {
			SQL::delete("bigtree_404s", array("redirect_url" => ""));
			AuditTrail::track("bigtree_404s", "all", "cleared-empty");
		}

		/*
			Function: create
				Creates a 301 redirect.

			Parameters:
				from - The 404 path
				to - The 301 target
				ignored - Whether to add the redirect to the ignored list (defaults to false)

			Returns:
				A Redirect object.
		*/

		static function create($from, $to = "", $ignored = false) {
			$from = htmlspecialchars(strip_tags(rtrim(str_replace(WWW_ROOT, "", $from), "/")));
			$to = htmlspecialchars(Link::encode($to));
			$ignored = $ignored ? "on" : "";

			// Try to convert the short URL into a full one
			if ($to !== "") {
				$redirect_url = $to;
				if (strpos($redirect_url, "//") === false) {
					$redirect_url = WWW_ROOT.ltrim($redirect_url, "/");
				}
				$redirect_url = htmlspecialchars(Link::encode($redirect_url));

				// Don't use static roots if they're the same as www just in case they are different when moving environments
				if (WWW_ROOT === STATIC_ROOT) {
					$redirect_url = str_replace("{staticroot}", "{wwwroot}", $redirect_url);
				}
			} else {
				$redirect_url = "";
			}

			// See if the from already exists
			$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ?", $from);

			if (!empty($existing)) {
				SQL::update("bigtree_404s", $existing["id"], array("redirect_url" => $redirect_url, "ignored" => $ignored));
				AuditTrail::track("bigtree_404s", $existing["id"], "updated");

				return new Redirect($existing["id"]);
			} else {
				$id = SQL::insert("bigtree_404s", array(
					"broken_url" => $from,
					"redirect_url" => $redirect_url,
					"ignored" => $ignored
				));
				
				AuditTrail::track("bigtree_404s", $id, "created");

				return new Redirect($id);
			}
		}

		/*
			Function: delete
				Deletes the redirect.
		*/

		function delete() {
			SQL::delete("bigtree_404s", $this->ID);
			AuditTrail::track("bigtree_404s", $this->ID, "deleted");
		}

		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		static function handle404($url) {
			$url = htmlspecialchars(strip_tags(rtrim($url, "/")));
			
			if (!$url) {
				return false;
			}

			$entry = SQL::fetch("SELECT * FROM bigtree_404s WHERE broken_url = ?", $url);
			
			// We already have a redirect
			if ($entry["redirect_url"]) {
				$entry["redirect_url"] = Link::iplEncode($entry["redirect_url"]);

				// If we're redirecting to the homepage, don't add additional trailing slashes
				if ($entry["redirect_url"] == "/") {
					$entry["redirect_url"] = "";
				}
				
				// Full URL, use the whole thing
				if (substr($entry["redirect_url"], 0, 7) == "http://" || substr($entry["redirect_url"], 0, 8) == "https://") {
					$redirect = $entry["redirect_url"];

					// Partial URL, append WWW_ROOT
				} else {
					$redirect = WWW_ROOT.str_replace(WWW_ROOT, "", $entry["redirect_url"]);
				}
				
				// Update request count
				SQL::query("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = ?", $entry["id"]);

				// Redirect with a 301
				Router::redirect(htmlspecialchars_decode($redirect), "301");

				// No redirect, log the 404 and throw the 404 headers
			} else {
				// Throw 404 header
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");

				if (!empty($entry)) {
					SQL::query("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = ?", $entry["id"]);
				} else {
					SQL::insert("bigtree_404s", array(
						"broken_url" => $url,
						"requests" => 1
					));
				}

				// Tell BigTree to not cache this page
				define("BIGTREE_DO_NOT_CACHE", true);
			}

			return true;
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// The create method already checks for existance and updates existing redirects
			$new = static::create($this->BrokenURL, $this->RedirectURL, $this->Ignored);
			$this->inherit($new);
		}

		/*
			Function: search
				Searches 404s, returns results.

			Parameters:
				type - The type of results (301, 404, or ignored).
				query - The search query.
				page - The page to return.
				return_arrays - Whether to return an array (true) or Redirect object (false)

			Returns:
				An array containing the number of pages of search results and one page of search results
		*/

		static function search($type, $query = "", $page = 1, $return_arrays = false) {
			if ($query) {
				$query = SQL::escape($query);
				if ($type == "301") {
					$where = "ignored = '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%') AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%')";
				} else {
					$where = "ignored = '' AND broken_url LIKE '%$query%' AND redirect_url = ''";
				}
			} else {
				if ($type == "301") {
					$where = "ignored = '' AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != ''";
				} else {
					$where = "ignored = '' AND redirect_url = ''";
				}
			}

			// Get the page count
			$result_count = SQL::fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_404s WHERE $where");
			$pages = ceil($result_count / 20);

			// Return 1 page even if there are 0
			$pages = $pages ? $pages : 1;

			// Get the results
			$results = SQL::fetchAll("SELECT * FROM bigtree_404s WHERE $where 
									  ORDER BY requests DESC LIMIT ".(($page - 1) * 20).",20");

			foreach ($results as &$result) {
				if ($return_arrays) {
					$result["redirect_url"] = Link::decode($result["redirect_url"]);
				} else {
					$result = new Redirect($result);
				}
			}

			return array($pages, $results);
		}

	}
