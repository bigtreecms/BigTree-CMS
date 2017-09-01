<?php
	/*
		Class: BigTree\Link
			Provides an interface for handling BigTree links.
	*/
	
	namespace BigTree;
	
	use DOMDocument;
	
	class Link {
		
		public static $IRLCache = [];
		public static $IPLCache = [];
		public static $TokenKeys = [];
		public static $TokenValues = [];
		
		private static $IRLsCreated = [];
		
		/*
			Function: byPath
				Returns the proper multi-site checked domain for a given page path.

			Parameters:
				path - A page path

			Returns:
				A string.
		*/
		
		static function byPath(string $path): string {
			global $bigtree;
			
			// Remove the site root from the path for multi-site
			if (defined("BIGTREE_SITE_KEY") || (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]))) {
				foreach (Router::$SiteRoots as $site_path => $site_data) {
					if ($site_path == "" || strpos($path, $site_path) === 0) {
						if ($site_path) {
							$path = substr($path, strlen($site_path) + 1);
						}
						
						if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
							return $site_data["www_root"].$path;
						}
						
						return $site_data["www_root"].$path."/";
					}
				}
			}
			
			if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
				return WWW_ROOT.$path;
			}
			
			return WWW_ROOT.$path."/";
		}
		
		/*
		    Function: cacheTokens
				Caches a list of tokens and the values that are related to them.
		*/
		
		static function cacheTokens(): void {
			global $bigtree;
			
			$valid_root = function ($root) {
				return (substr($root, 0, 7) == "http://" || substr($root, 0, 8) == "https://" || substr($root, 0, 2) == "//");
			};
			
			// Figure out what roots we can replace
			if (!count(static::$TokenKeys)) {
				if ($valid_root(ADMIN_ROOT)) {
					static::$TokenKeys[] = ADMIN_ROOT;
					static::$TokenValues[] = "{adminroot}";
				}
				
				if (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
					foreach ($bigtree["config"]["sites"] as $site_key => $site_configuration) {
						if ($valid_root($site_configuration["static_root"])) {
							static::$TokenKeys[] = $site_configuration["static_root"];
							static::$TokenValues[] = "{staticroot:$site_key}";
						}
						
						if ($valid_root($site_configuration["www_root"])) {
							static::$TokenKeys[] = $site_configuration["www_root"];
							static::$TokenValues[] = "{wwwroot:$site_key}";
						}
					}
				}
				
				if ($valid_root(STATIC_ROOT)) {
					static::$TokenKeys[] = STATIC_ROOT;
					static::$TokenValues[] = "{staticroot}";
				}
				
				if ($valid_root(WWW_ROOT)) {
					static::$TokenKeys[] = WWW_ROOT;
					static::$TokenValues[] = "{wwwroot}";
				}
			}
		}
		
		/*
			Function: currentURL
				Return the current active URL with correct protocall and port

			Parameters:
				port - Whether to return the port for connections not on port 80 (defaults to false)
		*/
		
		static function currentURL(bool $port = false): string {
			$protocol = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
			
			if ($_SERVER["SERVER_PORT"] != "80" && $port) {
				return $protocol.$_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
			} else {
				return $protocol.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
			}
		}
		
		/*
			Function: decode
				Replaces the internal page links in a string or array with hard links.
			
			Parameters:
				input - A string or array
			
			Returns:
				A string or array with internal page links decoded.
		*/
		
		static function decode($input) {
			// Allow for arrays to recurse
			if (is_array($input)) {
				foreach ($input as $key => $value) {
					$input[$key] = static::decode($value);
				}
				
				return $input;
			}
			
			// Save time if there's no content
			if (trim($input) === "") {
				return "";
			}
			
			if (substr($input, 0, 6) == "ipl://" || substr($input, 0, 6) == "irl://") {
				$input = static::iplDecode($input);
			} else {
				$input = static::detokenize($input);
				$input = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^', ['BigTree\Link', "decodeHook"], $input);
				$input = preg_replace_callback('^="(irl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^', ['BigTree\Link', "decodeHook"], $input);
			}
			
			return $input;
		}
		
		private static function decodeHook(array $matches): string {
			return '="'.static::iplDecode($matches[1]).'"';
		}
		
		/*
			Function: detokenize
				Replaces all root tokens in a URL (i.e. {wwwroot}) with hard links.

			Parameters:
				input - A string or array with root tokens.

			Returns:
				A string or array with hard links.
		*/
		
		static function detokenize($input) {
			if (is_array($input)) {
				foreach ($input as $key => $value) {
					$input[$key] = static::detokenize($value);
				}
				
				return $input;
			}
			
			static::cacheTokens();
			
			return str_replace(static::$TokenValues, static::$TokenKeys, $input);
		}
		
		/*
			Function: encode
				Converts links in a string or array into internal page links.

			Parameters:
				input - A string or array of contents that may contain URLs

			Returns:
				A string or array with hard links converted into internal page links.
		*/
		
		static function encode($input) {
			if (is_array($input)) {
				foreach ($input as $key => $value) {
					$input[$key] = static::encode($value);
				}
				
				return $input;
			}
			
			// If this string is actually just a URL, IPL it.
			if ((substr($input, 0, 7) == "http://" || substr($input, 0, 8) == "https://") && strpos($input, "\n") === false && strpos($input, "\r") === false) {
				$input = static::iplEncode($input);
			// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$input = preg_replace_callback('/href="([^"]*)"/', ['BigTree\Link', "encodeHref"], $input);
				$input = preg_replace_callback('/src="([^"]*)"/', ['BigTree\Link', "encodeSrc"], $input);
				$input = static::tokenize($input);
			}
			
			return $input;
		}
		
		private static function encodeHref(array $matches): string {
			$href = static::iplEncode(static::detokenize($matches[1]));
			
			return 'href="'.$href.'"';
		}
		
		private static function encodeSrc(array $matches): string {
			$src = static::iplEncode(static::detokenize($matches[1]));
			
			return 'src="'.$src.'"';
		}
		
		/*
			Function: get
				Returns the public link to a page in the database.
			
			Parameters:
				id - The ID of the page.
			
			Returns:
				Public facing URL.
		*/
		
		static function get(string $id): string {
			global $bigtree;
			
			// Homepage, just return the web root.
			if ($id === BIGTREE_SITE_TRUNK) {
				return WWW_ROOT;
			}
			
			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				return static::byPath($bigtree["page"]["path"]);
			}
			
			// Otherwise we'll grab the page data from the db.
			$page = SQL::fetch("SELECT path, template, external FROM bigtree_pages WHERE id = ? AND archived != 'on'", $id);
			
			if ($page) {
				if ($page["external"] !== "" && $page["template"] === "") {
					if (substr($page["external"], 0, 6) == "ipl://" || substr($page["external"], 0, 6) == "irl://") {
						$page["external"] = static::decode($page["external"]);
					}
					
					return $page["external"];
				}
				
				return static::byPath($page["path"]);
			}
			
			return false;
		}
		
		/*
			Function: getPreview
				Returns a URL to where this page can be previewed.

			Parameters:
				id - The ID of the page (or pending page)

			Returns:
				A URL.
		*/
		
		static function getPreview(string $id): string {
			if (substr($id, 0, 1) == "p") {
				return WWW_ROOT."_preview-pending/".htmlspecialchars($id)."/";
			} elseif ($id == 0) {
				return WWW_ROOT."_preview/";
			} else {
				$link = static::get($id);
				
				return str_replace(WWW_ROOT, WWW_ROOT."_preview/", $link);
			}
		}
		
		/*
			Function: integrity
				Checks a block of HTML for link/image intergirty

			Parameters:
				relative_path - The starting path of the page containing the HTML (so that relative links, i.e. "good/" know where to begin)
				html - A string of HTML
				external - Whether to check external links (slow) or not

			Returns:
				An array containing two possible keys (a and img) which each could contain an array of errors.
		*/
		
		static function integrity(string $relative_path, string $html, bool $external = false): array {
			if (empty($html)) {
				return [];
			}
			
			$errors = [];
			
			// Make sure HTML is valid.
			$doc = new DOMDocument();
			
			try {
				$doc->loadHTML($html);
			} catch (\Exception $e) {
				return [];
			}
			
			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = str_replace(["{wwwroot}", "%7Bwwwroot%7D", "{staticroot}", "%7Bstaticroot%7D"], [WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT], $href);
				
				if ((substr($href, 0, 2) == "//" || substr($href, 0, 4) == "http") && strpos($href, WWW_ROOT) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (strpos($href, "#") !== false) {
							$href = substr($href, 0, strpos($href, "#") - 1);
						}
						if (!static::urlExists($href)) {
							$errors["a"][] = $href;
						}
					}
				} elseif (substr($href, 0, 6) == "ipl://") {
					if (!static::iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href, 0, 6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href, 0, 7) == "mailto:" || substr($href, 0, 1) == "#" || substr($href, 0, 5) == "data:" || substr($href, 0, 4) == "tel:") {
					// Don't do anything, it's a page mark, data URI, or email address
				} elseif (substr($href, 0, 4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href, 0, 2) == "//") {
					// Protocol agnostic link
					if (!static::urlExists("http:".$href)) {
						$errors["a"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!static::urlExists($local)) {
						$errors["a"][] = $local;
					}
				}
			}
			
			// Check IMG tags.
			$images = $doc->getElementsByTagName("img");
			
			foreach ($images as $image) {
				$href = $image->getAttribute("src");
				$href = str_replace(["{wwwroot}", "%7Bwwwroot%7D", "{staticroot}", "%7Bstaticroot%7D"], [WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT], $href);
				
				if (substr($href, 0, 4) == "http" && strpos($href, WWW_ROOT) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!static::urlExists($href)) {
							$errors["img"][] = $href;
						}
					}
				} elseif (substr($href, 0, 6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["img"][] = $href;
					}
				} elseif (substr($href, 0, 5) == "data:") {
					// Do nothing, it's a data URI
				} elseif (substr($href, 0, 4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["img"][] = $href;
					}
				} elseif (substr($href, 0, 2) == "//") {
					// Protocol agnostic src
					if (!static::urlExists("http:".$href)) {
						$errors["img"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					
					if (!static::urlExists($local)) {
						$errors["img"][] = $local;
					}
				}
			}
			
			return $errors;
		}
		
		/*
			Function: iplDecode
				Returns a hard link to the page's publicly accessible URL from its encoded soft link URL.
			
			Parameters:
				ipl - Internal Page Link (ipl://, irl://, {wwwroot}, or regular URL encoding)
			
			Returns:
				Public facing URL.
		*/
		
		static function iplDecode(string $ipl): string {
			global $bigtree;
			
			// Regular links
			if (substr($ipl, 0, 6) != "ipl://" && substr($ipl, 0, 6) != "irl://") {
				return static::detokenize($ipl);
			}
			
			$ipl = explode("//", $ipl);
			$navid = $ipl[1];
			
			// Resource Links
			if ($ipl[0] == "irl:") {
				// See if it's in the cache.
				if (isset(static::$IRLCache[$navid])) {
					if ($ipl[2]) {
						return FileSystem::getPrefixedFile(static::$IRLCache[$navid], $ipl[2]);
					} else {
						return static::$IRLCache[$navid];
					}
				} else {
					$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $navid);
					$file = $resource ? static::detokenize($resource["file"]) : false;
					static::$IRLCache[$navid] = $file;
					
					if ($ipl[2]) {
						return FileSystem::getPrefixedFile($file, $ipl[2]);
					} else {
						return $file;
					}
				}
			}
			
			// New IPLs are encoded in JSON
			$c = json_decode(base64_decode($ipl[2]));
			
			// If it can't be rectified, we still don't want a warning.
			if (is_array($c) && count($c)) {
				$last = end($c);
				$commands = implode("/", $c);
				
				// If the URL's last piece has a GET (?), hash (#), or appears to be a file (.) don't add a trailing slash
				if ($bigtree["config"]["trailing_slash_behavior"] != "remove" && strpos($last, "#") === false && strpos($last, "?") === false && strpos($last, ".") === false) {
					$commands .= "/";
				}
			} else {
				$commands = "";
			}
			
			// See if it's in the cache.
			if (isset(static::$IPLCache[$navid])) {
				if ($bigtree["config"]["trailing_slash_behavior"] != "remove" || $commands != "") {
					return static::$IPLCache[$navid]."/".$commands;
				} else {
					return static::$IPLCache[$navid];
				}
			} else {
				// Get the page's path
				$path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $navid);
				
				// Set the cache
				static::$IPLCache[$navid] = rtrim(static::byPath($path), "/");
				
				if (!empty($bigtree["config"]["trailing_slash_behavior"]) && $bigtree["config"]["trailing_slash_behavior"] != "remove" || $commands != "") {
					return static::$IPLCache[$navid]."/".$commands;
				} else {
					return static::$IPLCache[$navid];
				}
			}
		}
		
		/*
			Function: iplEncode
				Creates an internal page link out of a URL.

			Parameters:
				url - A URL

			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/
		
		static function iplEncode(string $url): string {
			global $bigtree;

			$path_components = explode("/", rtrim(str_replace(WWW_ROOT, "", $url), "/"));
			
			// See if this is a file
			$local_path = str_replace(WWW_ROOT, SITE_ROOT, $url);
			
			if (($path_components[0] != "files" || $path_components[1] != "resources") &&
				(substr($local_path, 0, 1) == "/" || substr($local_path, 0, 2) == "\\\\") &&
				file_exists($local_path)) {
				
				return static::tokenize($url);
			}
			
			// If we have multiple sites, try each domain
			if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
				foreach ($bigtree["config"]["sites"] as $site_key => $configuration) {
					// This is the site we're pointing to
					if (strpos($url, $configuration["www_root"]) !== false) {
						$path_components = explode("/", rtrim(str_replace($configuration["www_root"], "", $url), "/"));
						
						// Check for resource link
						if ($path_components[0] == "files" && $path_components[1] == "resources") {
							$resource = Resource::getByFile($url);
							
							if ($resource) {
								static::$IRLsCreated[] = $resource["id"];
								
								return "irl://".$resource["id"]."//".$resource["prefix"];
							}
						}
						
						// Get the root path of the site for calculating an IPL and add it to the path components
						$root_path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $configuration["trunk"]);
						$path_components = array_filter(array_merge(explode("/", $root_path), $path_components));
						
						// Check for page link
						list($navid, $commands) = Router::routeToPage($path_components);
						
						if ($navid) {
							return "ipl://".$navid."//".base64_encode(json_encode($commands));
						} else {
							return static::tokenize($url);
						}
					}
				}
				
				return static::tokenize($url);
			} else {
				// Check for resource link
				if ($path_components[0] == "files" && $path_components[1] == "resources") {
					$resource = Resource::getByFile($url);
					
					if ($resource) {
						static::$IRLsCreated[] = $resource["id"];
						
						return "irl://".$resource["id"]."//".$resource["prefix"];
					}
				}
				
				// Check for page link
				list($navid, $commands) = Router::routeToPage($path_components);
			}
			
			if (!$navid) {
				return static::tokenize($url);
			}
			
			return "ipl://".$navid."//".base64_encode(json_encode($commands));
		}
		
		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.

			Parameters:
				ipl - An internal page link

			Returns:
				True if it is still a valid link, otherwise false.
		*/
		
		static function iplExists(string $ipl): bool {
			$ipl = explode("//", $ipl);
			
			// See if the page it references still exists.
			$nav_id = $ipl[1];
			
			if (!SQL::exists("bigtree_pages", $nav_id)) {
				return false;
			}
			
			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]), true);
			
			// If there are no commands, we're good.
			if (empty($commands[0])) {
				return true;
			}
			
			// If it's a hash tag link, we're also good.
			if (substr($commands[0], 0, 1) == "#") {
				return true;
			}
			
			// Get template for the navigation id to see if it's a routed template
			$routed = SQL::fetchSingle("SELECT bigtree_templates.routed FROM bigtree_templates JOIN bigtree_pages 
													ON bigtree_templates.id = bigtree_pages.template 
													WHERE bigtree_pages.id = ?", $nav_id);
			// If we're a routed template, we're good.
			if ($routed) {
				return true;
			}
			
			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
			return false;
		}
		
		/*
			Function: irlExists
				Determines whether an internal resource link still exists or not.

			Parameters:
				irl - An internal resource link

			Returns:
				True if it is still a valid link, otherwise false.
		*/
		
		static function irlExists(string $irl): bool {
			$irl = explode("//", $irl);
			
			return Resource::exists($irl[1]) ? true : false;
		}
		
		/*
			Function: isExternal
				Check if URL is external, relative to site root

			Parameters:
				url - The URL to test.

			Returns:
				true if link is external
		*/
		
		static function isExternal(?string $url): bool {
			if (is_null($url)) {
				return false;
			}
			
			if (substr($url, 0, 7) != "http://" && substr($url, 0, 8) != "https://") {
				return false;
			}
			
			$www_root = str_replace(["https://", "http://"], "//", WWW_ROOT);
			$url = str_replace(["https://", "http://"], "//", $url);
			
			if (strpos($url, $www_root) === 0) {
				return false;
			}
			
			return true;
		}
		
		/*
			Function: stripMultipleRootTokens
				Strips the multi-domain root tokens from a string and replaces them with standard {wwwroot} and {staticroot}

			Parameters:
				string - A string

			Returns:
				A modified string.
		*/
		
		static function stripMultipleRootTokens(string $string): string {
			global $bigtree;
			
			if (empty($bigtree["config"]["sites"]) || !array_filter((array) $bigtree["config"]["sites"])) {
				return $string;
			}
			
			foreach ($bigtree["config"]["sites"] as $key => $data) {
				$string = str_replace(
					["{wwwroot:$key}", "{staticroot:$key}"],
					["{wwwroot}", "{staticroot}"],
					$string
				);
			}
			
			return $string;
		}
		
		/*
			Function: tokenize
				Replaces all hard roots in a URL with tokens (i.e. {wwwroot}).

			Parameters:
				input - A string or array with hard roots.

			Returns:
				A string or array with tokens.
		*/
		
		static function tokenize($input) {
			if (is_array($input)) {
				foreach ($input as $key => $value) {
					$input[$key] = static::tokenize($value);
				}
				
				return $input;
			}
			
			static::cacheTokens();
			
			return str_replace(static::$TokenKeys, static::$TokenValues, $input);
		}
		
		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.
		*/
		
		static function urlExists(string $url): bool {
			// Handle // urls as http://
			if (substr($url, 0, 2) == "//") {
				$url = "http:".$url;
			}
			
			$handle = curl_init($url);
			if ($handle === false) {
				return false;
			}
			
			// We want just the header (NOBODY sets it to a HEAD request)
			curl_setopt($handle, CURLOPT_HEADER, true);
			curl_setopt($handle, CURLOPT_NOBODY, true);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			
			// Fail on error should make it so response codes > 400 result in a fail
			curl_setopt($handle, CURLOPT_FAILONERROR, true);
			
			// Request as Firefox so that servers don't reject us for not having headers.
			curl_setopt($handle, CURLOPT_HTTPHEADER, ["User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15"]);
			
			// Execute the request and close the handle
			$success = curl_exec($handle) ? true : false;
			curl_close($handle);
			
			return $success;
		}
		
		/*
			Function: urlify
				Turns a string into one suited for URL routes.
			
			Parameters:
				title - A short string.
			
			Returns:
				A string suited for a URL route.
		*/
		
		static function urlify(string $title): string {
			$replacements = [
				'Â' => 'A',
				'Ã' => 'A',
				'Ä' => 'A',
				'À' => 'A',
				'Á' => 'A',
				'Å' => 'A',
				'Æ' => 'AE',
				'Ç' => 'C',
				'È' => 'E',
				'É' => 'E',
				'Ê' => 'E',
				'Ë' => 'E',
				'Ì' => 'I',
				'Í' => 'I',
				'Î' => 'I',
				'Ï' => 'I',
				'Ð' => 'D',
				'Ñ' => 'N',
				'Ò' => 'O',
				'Ó' => 'O',
				'Ô' => 'O',
				'Õ' => 'O',
				'Ö' => 'O',
				'Ø' => 'O',
				'Ù' => 'U',
				'Ú' => 'U',
				'Û' => 'U',
				'Ü' => 'U',
				'Ý' => 'Y',
				'ß' => 'B',
				'à' => 'a',
				'á' => 'a',
				'â' => 'a',
				'ã' => 'a',
				'ä' => 'a',
				'å' => 'a',
				'æ' => 'ae',
				'ç' => 'c',
				'è' => 'e',
				'é' => 'e',
				'ê' => 'e',
				'ë' => 'e',
				'ì' => 'i',
				'í' => 'i',
				'î' => 'i',
				'ï' => 'i',
				'ð' => 'o',
				'ñ' => 'n',
				'ò' => 'o',
				'ó' => 'o',
				'ô' => 'o',
				'õ' => 'o',
				'ö' => 'o',
				'ø' => 'o',
				'ù' => 'u',
				'ú' => 'u',
				'û' => 'u',
				'ü' => 'u',
				'ý' => 'y',
				'ÿ' => 'y'
			];
			
			$title = strtr($title, $replacements);
			$title = htmlspecialchars_decode($title);
			$title = str_replace("/", "-", $title);
			$title = strtolower(preg_replace('/\s/', '-', preg_replace('/[^a-zA-Z0-9\s\-\_]+/', '', trim($title))));
			$title = str_replace("--", "-", $title);
			
			return $title;
		}
	}
