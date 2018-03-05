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
		
		static function catch404(): void {
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
		
		static function clearEmpty(): void {
			SQL::delete("bigtree_404s", ["redirect_url" => ""]);
			AuditTrail::track("bigtree_404s", "all", "cleared-empty");
		}
		
		/*
			Function: create
				Creates a 301 redirect.

			Parameters:
				from - The 404 path
				to - The 301 target
				site_key - The site key for a multi-site environment (defaults to null)
				ignored - Whether to add the redirect to the ignored list (defaults to false)

			Returns:
				A Redirect object.
		*/
		
		static function create(string $from, ?string $to = "", ?string $site_key = null, bool $ignored = false): Redirect {
			global $bigtree;
			
			// If this is a multi-site environment and a full URL was pasted in we're going to auto-select the key no matter what they passed in
			if (!is_null($site_key)) {
				$from_domain = parse_url($from, PHP_URL_HOST);
				
				foreach ($bigtree["config"]["sites"] as $index => $site) {
					$domain = parse_url($site["domain"], PHP_URL_HOST);
					
					if ($domain == $from_domain) {
						$site_key = $index;
						$from = str_replace($site["www_root"], "", $from);
					}
				}
			}
			
			// Allow for from URLs with GET vars
			$from_parts = parse_url($from);
			$get_vars = "";
			
			if (!empty($from_parts["query"])) {
				$from = str_replace("?".$from_parts["query"], "", $from);
				$get_vars = sqlescape(htmlspecialchars($from_parts["query"]));
			}
			
			$from = htmlspecialchars(strip_tags(rtrim(str_replace(WWW_ROOT, "", $from), "/")));
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
			if ($get_vars) {
				if (!is_null($site_key)) {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND site_key = ? AND get_vars = ?",
										   $from, $site_key, $get_vars);
				} else {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ?", $from, $get_vars);
				}
			} else {
				if (!is_null($site_key)) {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND site_key = ?", $from, $site_key);
				} else {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ?", $from);
				}
			}
			
			if (!empty($existing)) {
				SQL::update("bigtree_404s", $existing["id"], ["redirect_url" => $redirect_url, "ignored" => $ignored]);
				AuditTrail::track("bigtree_404s", $existing["id"], "updated");
				
				return new Redirect($existing["id"]);
			} else {
				$id = SQL::insert("bigtree_404s", [
					"broken_url" => $from,
					"get_vars" => $get_vars,
					"redirect_url" => $redirect_url,
					"ignored" => $ignored,
					"site_key" => $site_key
				]);
				
				AuditTrail::track("bigtree_404s", $id, "created");
				
				return new Redirect($id);
			}
		}
		
		/*
			Function: delete
				Deletes the redirect.
		*/
		
		function delete(): ?bool {
			SQL::delete("bigtree_404s", $this->ID);
			AuditTrail::track("bigtree_404s", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		static function handle404(string $url): bool {
			$url = sqlescape(htmlspecialchars(strip_tags(rtrim($url, "/"))));
			$existing = null;
			
			if (!$url) {
				return true;
			}
			
			// See if there's any GET requests
			$get = $_GET;
			unset($get["bigtree_htaccess_url"]);
			
			if (count($get)) {
				$query_pieces = [];
				
				foreach ($get as $key => $value) {
					$query_pieces[] = $key."=".$value;
				}
				
				$get = Text::htmlEncode(implode("&", $query_pieces));
				
				if (defined("BIGTREE_SITE_KEY")) {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE broken_url = ? AND get_vars = ? AND site_key = ?",
											$url, $get, BIGTREE_SITE_KEY);
				} else {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE broken_url = ? AND get_vars = ?", $url, $get);
				}
				
				// Look for a 404 that has a redirect but no get vars
				if (empty($existing["redirect_url"])) {
					if (defined("BIGTREE_SITE_KEY")) {
						$non_get_existing = SQL::fetch("SELECT * FROM bigtree_404s
														WHERE broken_url = ? AND redirect_url != ''
														  AND get_vars = ? AND site_key = ?",
													   $url, $get, BIGTREE_SITE_KEY);
					} else {
						$non_get_existing = SQL::fetch("SELECT * FROM bigtree_404s
														WHERE broken_url = ? AND redirect_url != '' AND get_vars = ?",
													   $url, $get);
					}
					
					if ($non_get_existing) {
						$existing = $non_get_existing;
					}
				}
			} else {
				$get = "";
				
				if (defined("BIGTREE_SITE_KEY")) {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s
											WHERE broken_url = ? AND get_vars = '' AND site_key = ?",
											$url, BIGTREE_SITE_KEY);
				} else {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s WHERE broken_url = ? AND get_vars = ''", $url);
				}
			}
			
			if ($existing["redirect_url"]) {
				$existing["redirect_url"] = Link::decode($existing["redirect_url"]);
				
				if ($existing["redirect_url"] == "/") {
					$existing["redirect_url"] = "";
				}
				
				if (substr($existing["redirect_url"],0,7) == "http://" || substr($existing["redirect_url"],0,8) == "https://") {
					$redirect = $existing["redirect_url"];
				} else {
					$redirect = WWW_ROOT.str_replace(WWW_ROOT, "", $existing["redirect_url"]);
				}
				
				SQL::query("bigtree_404s SET requests = (requests + 1) WHERE id = ?", $existing["id"]);
				Router::redirect(htmlspecialchars_decode($redirect), "301");
				
				return false;
			} else {
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				define("BIGTREE_DO_NOT_CACHE", true);
				define("BIGTREE_URL_IS_404", true);
				
				if ($existing && $existing["get_vars"] == $get) {
					SQL::query("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = ?", $existing["id"]);
				} elseif (defined("BIGTREE_SITE_KEY")) {
					SQL::insert("bigtree_404s", [
						"broken_url" => $url,
						"get_vars" => $get,
						"requests" => 1,
						"site_key" => BIGTREE_SITE_KEY
					]);
				} else {
					SQL::insert("bigtree_404s", [
						"broken_url" => $url,
						"get_vars" => $get,
						"requests" => 1
					]);
				}
				
				return true;
			}
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		function save(): ?bool {
			// The create method already checks for existance and updates existing redirects
			$new = static::create($this->BrokenURL, $this->RedirectURL, $this->Ignored);
			$this->inherit($new);
			
			return true;
		}
		
		/*
			Function: search
				Searches 404s, returns results.

			Parameters:
				type - The type of results (301, 404, or ignored).
				query - The search query.
				page - The page to return.
				site_key - The site key to return 404s for (leave null for all 404s).
				return_arrays - Whether to return an array (true) or Redirect object (false)

			Returns:
				An array containing the number of pages of search results and one page of search results
		*/
		
		static function search(string $type, string $query = "", int $page = 1, ?string $site_key = null,
							   bool $return_arrays = false): array {
			if (!is_null($site_key)) {
				$site_key_query = "AND site_key = '".SQL::escape($site_key)."'";
			} else {
				$site_key_query = "";
			}

			if ($query) {
				$query = SQL::escape($query);
				
				if ($type == "301") {
					$where = "ignored = '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%' OR get_vars LIKE '%$query%') AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%' OR get_vars LIKE '%$query%')";
				} else {
					$where = "ignored = '' AND (broken_url LIKE '%$query%' OR get_vars LIKE '%$query%') AND redirect_url = ''";
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
			$result_count = SQL::fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_404s WHERE $where $site_key_query");
			$pages = ceil($result_count / 20);
			
			// Return 1 page even if there are 0
			$pages = $pages ? $pages : 1;
			
			// Get the results
			$results = SQL::fetchAll("SELECT * FROM bigtree_404s WHERE $where $site_key_query
									  ORDER BY requests DESC LIMIT ".(($page - 1) * 20).",20");
			
			foreach ($results as &$result) {
				if ($return_arrays) {
					$result["redirect_url"] = Link::decode($result["redirect_url"]);
				} else {
					$result = new Redirect($result);
				}
			}
			
			return [$pages, $results];
		}
		
	}
