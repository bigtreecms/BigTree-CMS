<?php
	/*
		Class: BigTree\Link
			Provides an interface for handling BigTree links.
	*/

	namespace BigTree;

	use DOMDocument;

	class Link {

		public static $IRLCache = array();
		public static $IPLCache = array();
		public static $TokenKeys = array();
		public static $TokenValues = array();

		/*
			Function: currentURL
				Return the current active URL with correct protocall and port

			Parameters:
				port - Whether to return the port for connections not on port 80 (defaults to false)
		*/

		static function currentURL($port = false) {
			$protocol = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
			
			if ($_SERVER["SERVER_PORT"] != "80" && $port) {
				return $protocol.$_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
			} else {
				return $protocol.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
			}
		}
		
		/*
			Function: decode
				Replaces the internal page links in a string with hard links.
			
			Parameters:
				html - An HTML block
			
			Returns:
				An HTML block with links hard-linked.
		*/
		
		static function decode($html) {
			// Save time if there's no content
			if (trim($html) === "") {
				return "";
			}
			
			if (substr($html, 0, 6) == "ipl://" || substr($html, 0, 6) == "irl://") {
				$html = static::iplDecode($html);
			} else {
				$html = static::detokenize($html);
				$html = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^', array('BigTree\Link', "decodeHook"), $html);
				$html = preg_replace_callback('^="(irl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^', array('BigTree\Link', "decodeHook"), $html);
			}

			return $html;
		}

		private static function decodeHook($matches) {
			return '="'.static::iplDecode($matches[1]).'"';
		}

		/*
			Function: decodeArray
				Steps through an array and creates hard links for all internal page links.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links decoded.
			
			See Also:
				<translateArray>
		*/
		
		static function decodeArray($array) {
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = static::decodeArray($piece);
				} else {
					$piece = static::decode($piece);
				}
			}
			
			return $array;
		}

		/*
			Function: detokenize
				Replaces all root tokens in a URL (i.e. {wwwroot}) with hard links.

			Parameters:
				string - A string with root tokens.

			Returns:
				A string with hard links.
		*/

		static function detokenize($string) {
			return str_replace(array("{adminroot}", "{wwwroot}", "{staticroot}"), array(ADMIN_ROOT, WWW_ROOT, STATIC_ROOT), $string);
		}

		/*
			Function: encode
				Converts links in a string into internal page links.

			Parameters:
				string - A string of contents that may contain URLs

			Returns:
				A string with hard links converted into internal page links.
		*/

		static function encode($string) {
			// If this string is actually just a URL, IPL it.
			if ((substr($string, 0, 7) == "http://" || substr($string, 0, 8) == "https://") && strpos($string, "\n") === false && strpos($string, "\r") === false) {
				$string = static::iplEncode($string);
				// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$string = preg_replace_callback('/href="([^"]*)"/', array('BigTree\Link', "encodeHref"), $string);
				$string = preg_replace_callback('/src="([^"]*)"/', array('BigTree\Link', "encodeSrc"), $string);
				$string = static::tokenize($string);
			}

			return $string;
		}
		
		private static function encodeHref($matches) {
			$href = static::iplEncode(static::detokenize($matches[1]));

			return 'href="'.$href.'"';
		}

		private static function encodeSrc($matches) {
			$src = static::iplEncode(static::detokenize($matches[1]));

			return 'src="'.$src.'"';
		}

		/*
			Function: encodeArray
				Steps through an array and creates internal page links for all parts of it.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links encoded.
			
			See Also:
				<untranslateArray>
		*/
		
		static function encodeArray($array) {
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = static::encodeArray($piece);
				} else {
					$piece = static::encode($piece);
				}
			}

			return $array;
		}

		/*
			Function: get
				Returns the public link to a page in the database.
			
			Parameters:
				id - The ID of the page.
			
			Returns:
				Public facing URL.
		*/
		
		static function get($id) {
			global $bigtree;

			// Homepage, just return the web root.
			if ($id == 0) {
				return WWW_ROOT;
			}

			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
					return WWW_ROOT.$bigtree["page"]["path"];
				} else {
					return WWW_ROOT.$bigtree["page"]["path"]."/";
				}
			}

			// Otherwise we'll grab the page path from the db.
			$path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE archived != 'on' AND id = ?", $id);

			if ($path) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
					return WWW_ROOT.$path;
				} else {
					return WWW_ROOT.$path."/";
				}
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

		static function getPreview($id) {
			if (substr($id, 0, 1) == "p") {
				return WWW_ROOT."_preview-pending/".htmlspecialchars($id)."/";
			} elseif ($id == 0) {
				return WWW_ROOT."_preview/";
			} else {
				$path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $id);

				return WWW_ROOT."_preview/$path/";
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

		static function integrity($relative_path, $html, $external = false) {
			if (!$html) {
				return array();
			}
			$errors = array();

			// Make sure HTML is valid.
			$doc = new DOMDocument();
			try {
				$doc->loadHTML($html);
			} catch (\Exception $e) {
				return array();
			}

			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = str_replace(array("{wwwroot}", "%7Bwwwroot%7D", "{staticroot}", "%7Bstaticroot%7D"), array(WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT), $href);
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
				$href = str_replace(array("{wwwroot}", "%7Bwwwroot%7D", "{staticroot}", "%7Bstaticroot%7D"), array(WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT), $href);
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
		
		static function iplDecode($ipl) {
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
				static::$IPLCache[$navid] = WWW_ROOT.$path;

				if (!empty($bigtree["config"]["trailing_slash_behavior"]) && $bigtree["config"]["trailing_slash_behavior"] != "remove" || $commands != "") {
					return WWW_ROOT.$path."/".$commands;
				} else {
					return WWW_ROOT.$path;
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

		static function iplEncode($url) {
			// See if this is a file
			$local_path = str_replace(WWW_ROOT, SITE_ROOT, $url);
			if ((substr($local_path, 0, 1) == "/" || substr($local_path, 0, 2) == "\\\\") && file_exists($local_path)) {
				return static::tokenize($url);
			}

			$command = explode("/", rtrim(str_replace(WWW_ROOT, "", $url), "/"));
			
			// Check for resource link
			if ($command[0] == "files" && $command[1] == "resources") {
				$resource = Resource::file($url);
				if ($resource) {
					Resource::$CreationLog[] = $resource["id"];

					return "irl://".$resource["id"]."//".$resource["prefix"];
				}
			}

			// Check for page link
			list($navid, $commands) = Router::routeToPage($command);
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

		static function iplExists($ipl) {
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

		static function irlExists($irl) {
			$irl = explode("//", $irl);

			return Resource::get($irl[1]) ? true : false;
		}

		/*
			Function: isExternal
				Check if URL is external, relative to site root

			Parameters:
				url - The URL to test.

			Returns:
				true if link is external
		*/

		static function isExternal($url) {
			if (substr($url, 0, 7) != "http://" && substr($url, 0, 8) != "https://") {
				return false;
			}

			$www_root = str_replace(array("https://", "http://"), "//", WWW_ROOT);
			$url = str_replace(array("https://", "http://"), "//", $url);

			if (strpos($url, $www_root) === 0) {
				return false;
			}

			return true;
		}

		/*
			Function: tokenize
				Replaces all hard roots in a URL with tokens (i.e. {wwwroot}).

			Parameters:
				string - A string with hard roots.

			Returns:
				A string with tokens.
		*/

		static function tokenize($string) {
			// Figure out what roots we can replace
			if (!count(static::$TokenKeys)) {
				if (substr(ADMIN_ROOT, 0, 7) == "http://" || substr(ADMIN_ROOT, 0, 8) == "https://") {
					static::$TokenKeys[] = ADMIN_ROOT;
					static::$TokenValues[] = "{adminroot}";
				}
				if (substr(STATIC_ROOT, 0, 7) == "http://" || substr(STATIC_ROOT, 0, 8) == "https://") {
					static::$TokenKeys[] = STATIC_ROOT;
					static::$TokenValues[] = "{staticroot}";
				}
				if (substr(WWW_ROOT, 0, 7) == "http://" || substr(WWW_ROOT, 0, 8) == "https://") {
					static::$TokenKeys[] = WWW_ROOT;
					static::$TokenValues[] = "{wwwroot}";
				}
			}

			return str_replace(static::$TokenKeys, static::$TokenValues, $string);
		}

		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.
		*/

		static function urlExists($url) {
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
			curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15"));

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

		static function urlify($title) {
			$accent_match = array('Â', 'Ã', 'Ä', 'À', 'Á', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
			$accent_replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'B', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');

			$title = str_replace($accent_match, $accent_replace, $title);
			$title = htmlspecialchars_decode($title);
			$title = str_replace("/", "-", $title);
			$title = strtolower(preg_replace('/\s/', '-', preg_replace('/[^a-zA-Z0-9\s\-\_]+/', '', trim($title))));
			$title = str_replace("--", "-", $title);

			return $title;
		}
	}
