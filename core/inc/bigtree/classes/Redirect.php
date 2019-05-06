<?php
	/*
		Class: BigTree\Redirect
			Provides an interface for handling BigTree 404s and 301s.
	*/
	
	namespace BigTree;
	
	class Redirect extends SQLObject
	{
		
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
		
		public function __construct($redirect = null)
		{
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
		
		public static function catch404(): void
		{
			Router::checkPathHistory(Router::$Path);
			
			// Wipe any content that's already been drawn
			ob_clean();
			
			if (static::handle404(str_ireplace(WWW_ROOT, "", Link::currentURL()))) {
				Router::$Layout = "default";
				include SERVER_ROOT."templates/basic/_404.php";
				
				// Get content and start the buffer again
				Router::$Content = ob_get_clean();
				ob_start();
				
				// Draw content in the provided layout
				include "../templates/layouts/".Router::$Layout.".php";
				die();
			}
		}
		
		/*
			Function: clearEmpty
				Removes all 404s that don't have 301 redirects.
		*/
		
		public static function clearEmpty(): void
		{
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
		
		public static function create(string $from, ?string $to = "", ?string $site_key = null,
									  bool $ignored = false): Redirect
		{
			$sanitized_input = static::parseSourceURL($from, $site_key);
			$from = $sanitized_input["url"];
			$get_vars = $sanitized_input["get_vars"];
			$site_key = $sanitized_input["site_key"];
			$to = htmlspecialchars(Link::encode(trim($to)));
			$existing = static::getExisting($from, $get_vars, $site_key);
			$history_cleaned = false;
			
			if ($site_key) {
				foreach (Router::$SiteRoots as $site_path => $data) {
					if ($data["key"] == $site_key) {
						$history_cleaned = true;
						SQL::delete("bigtree_route_history", ["old_route" => ltrim($site_path."/".$from, "/")]);
					}
				}
			}
			
			if (!$history_cleaned) {
				SQL::delete("bigtree_route_history", ["old_route" => $from]);
			}
			
			if ($existing) {
				$id = $existing["id"];
				
				SQL::update("bigtree_404s", $existing["id"], [
					"redirect_url" => $to,
					"ignored" => $ignored ? "on" : ""
				]);
				AuditTrail::track("bigtree_404s", $existing["id"], "updated");
			} else {
				$id = SQL::insert("bigtree_404s", [
					"broken_url" => $from,
					"get_vars" => $get_vars,
					"redirect_url" => $to,
					"ignored" => $ignored ? "on" : "",
					"site_key" => $site_key
				]);
				
				AuditTrail::track("bigtree_404s", $id, "created");
			}
				
			return new Redirect($id);
		}
		
		/*
			Function: delete
				Deletes the redirect.
		*/
		
		public function delete(): ?bool
		{
			SQL::delete("bigtree_404s", $this->ID);
			AuditTrail::track("bigtree_404s", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: getExisting
				Checks for the existance of a 404 at a given source URL.

			Parameters:
				url - Source URL
				get_vars - Source URL get vars
				site_key - Optional site key for a multi-site environment.

			Returns:
				An existing 404 or null if one is not found.
		*/
		
		static public function getExisting(string $url, string $get_vars, ?string $site_key = null)
		{
			if (!empty($get_vars)) {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s
									   WHERE `broken_url` = ? AND get_vars = ? AND `site_key` = ?",
									  $url, $get_vars, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s
									   WHERE `broken_url` = ? AND get_vars = ?", $url, $get_vars);
				}
			} else {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s
									   WHERE `broken_url` = ? AND get_vars = '' AND `site_key` = ?", $url, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ''", $url);
				}
			}
		}
		
		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		public static function handle404(string $url): bool
		{
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
					$existing = SQL::fetch("SELECT * FROM bigtree_404s
											WHERE broken_url = ? AND get_vars = ? AND site_key = ?",
											$url, $get, BIGTREE_SITE_KEY);
				} else {
					$existing = SQL::fetch("SELECT * FROM bigtree_404s
											WHERE broken_url = ? AND get_vars = ?", $url, $get);
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
			Function: parseSourceURL
				Parses a 404 source URL based on site key.

			Parameters:
				source - Source URL or URL fragment
				site_key - Optional site key

			Returns:
				An array containing the sanitized source URL for input into bigtree_404s, inferred site key, and GET variables from the URL.
		*/
		
		public static function parseSourceURL(string $source, ?string $site_key = null): array {
			global $bigtree;
			
			$source = trim($source);
			
			// If this is a multi-site environment and a full URL was pasted in we're going to auto-select the key no matter what they passed in
			if (!is_null($site_key)) {
				$from_domain = parse_url($source, PHP_URL_HOST);
				
				foreach ($bigtree["config"]["sites"] as $index => $site) {
					$domain = parse_url($site["domain"], PHP_URL_HOST);
					
					if ($domain == $from_domain) {
						$site_key = $index;
						$source = str_replace($site["www_root"], "", $source);
						
						break;
					}
				}
			}
			
			// Allow for from URLs with GET vars
			$source_parts = parse_url($source);
			$get_vars = "";
			
			if (!empty($source_parts["query"])) {
				$source = str_replace("?".$source_parts["query"], "", $source);
				$get_vars = sqlescape(htmlspecialchars($source_parts["query"]));
			}
			
			return [
				"url" => htmlspecialchars(strip_tags(trim(str_replace(WWW_ROOT, "", $source), "/"))),
				"get_vars" => $get_vars,
				"site_key" => $site_key
			];
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
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
		
		public static function search(string $type, string $query = "", int $page = 1, ?string $site_key = null,
									  bool $return_arrays = false): array
		{
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
