<?php
	/*
		Class: BigTree\Router
			Provides an interface for handling BigTree routing.
	*/

	namespace BigTree;

	use BigTree;

	class Router {

		protected static $ReservedRoutes = array();

		public static $Registry = false;
		public static $RouteParamNames = array();
		public static $RouteParamNamesPath = array();
		public static $Secure = false;

		/*
			Function: checkPathHistory
				Checks the page route history table, redirects if the page is found.
			
			Parameters:
				path - An array of routes
		*/
		
		static function checkPathHistory($path) {
			$found = $new = $old = false;
			$x = count($path);

			while ($x) {
				$result = SQL::fetch("SELECT * FROM bigtree_route_history WHERE old_route = ?", implode("/", array_slice($path, 0, $x)));

				if ($result) {
					$old = $result["old_route"];
					$new = $result["new_route"];
					$found = true;

					break;
				}

				$x--;
			}

			// If it's in the old routing table, send them to the new page.
			if ($found) {
				$new_url = $new.substr($_GET["bigtree_htaccess_url"], strlen($old));
				static::redirect(WWW_ROOT.$new_url, "301");
			}
		}

		/*
			Function: clearCache
				Removes all files in the cache directory removing cached pages and module routes.
		*/

		static function clearCache() {
			$d = opendir(SERVER_ROOT."cache/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != ".." && !is_dir(SERVER_ROOT."cache/".$f)) {
					unlink(SERVER_ROOT."cache/".$f);
				}
			}
		}

		/*
			Function: forceHTTPS
				Forces the site into Secure mode to be served over HTTPS.
				When Secure mode is enabled, BigTree will enforce the user being at HTTPS and will rewrite all insecure resources (like CSS, JavaScript, and images) to use HTTPS.
		*/
		
		static function forceHTTPS() {
			if (!$_SERVER["HTTPS"]) {
				static::redirect("https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"], "301");
			}

			static::$Secure = true;
		}

		/*
			Function: getIncludePath
				Get the proper path for a file based on whether a custom override exists.

			Parameters:
				file - File path relative to either core/ or custom/

			Returns:
				Hard file path to a custom/ (preferred) or core/ file depending on what exists.
		*/

		static function getIncludePath($file) {
			if (file_exists(SERVER_ROOT."custom/".$file)) {
				return SERVER_ROOT."custom/".$file;
			} else {
				return SERVER_ROOT."core/".$file;
			}
		}

		/*
			Function: getRegistryCommands
				Helper function for pattern based routing.
		*/

		static function getRegistryCommands($path, $pattern) {
			// This method is based almost entirely on the Slim Framework's routing implementation (http://www.slimframework.com/)
			static::$RouteParamNames = array();
			static::$RouteParamNamesPath = array();

			// Convert URL params into regex patterns, construct a regex for this route, init params
			$regex_pattern = preg_replace_callback('#:([\w]+)\+?#', "BigTree\\Router::getRegistryCommandsCallback", str_replace(')', ')?', $pattern));

			if (substr($pattern, -1) === '/') {
				$regex_pattern .= '?';
			}

			$regex = '#^'.$regex_pattern.'$#';

			// Do the regex match
			if (!preg_match($regex, $path, $values)) {
				return false;
			}

			$params = array();
			foreach (static::$RouteParamNames as $name) {
				if (isset($values[$name])) {
					if (isset(static::$RouteParamNamesPath[$name])) {
						$params[$name] = explode('/', urldecode($values[$name]));
					} else {
						$params[$name] = urldecode($values[$name]);
					}
				}
			}

			return $params;
		}

		/*
			Function: getRegistryCommandsCallback
				Regex callback for getRegistryCommands
		*/

		static function getRegistryCommandsCallback($match) {
			static::$RouteParamNames[] = $match[1];
			
			if (substr($match[0], -1) === '+') {
				static::$RouteParamNamesPath[$match[1]] = 1;

				return '(?P<'.$match[1].'>.+)';
			}

			return '(?P<'.$match[1].'>[^/]+)';
		}

		/*
		 	Function: getReservedRoutes
				Returns an array of already reserved top level routes.

			Returns:
				An array of strings.
		*/

		static function getReservedRoutes() {
			// Already cached them
			if (count(static::$ReservedRoutes)) {
				return static::$ReservedRoutes;
			}

			static::$ReservedRoutes = array(
				"ajax",
				"css",
				"feeds",
				"js",
				"sitemap.xml",
				"_preview",
				"_preview-pending"
			);

			// Update the reserved top level routes with the admin's route
			list($admin_route) = explode("/", str_replace(WWW_ROOT, "", rtrim(ADMIN_ROOT, "/")));
			static::$ReservedRoutes[] = $admin_route;

			return static::$ReservedRoutes;
		}

		/*
			Function: getRoutedFileAndCommands
				Returns the proper file to include based on existence of subdirectories or .php files with given route names.
				Used by the CMS for routing ajax and modules.

			Parameters:
				directory - Root directory to begin looking in.
				path - An array of routes.

			Returns:
				An array with the first element being the file to include and the second element being an array containing extraneous routes from the end of the path.
		*/

		static function getRoutedFileAndCommands($directory, $path) {
			$commands = array();
			$inc_file = $directory;
			$inc_dir = $directory;
			$ended = false;
			$found_file = false;

			foreach ($path as $piece) {
				// Prevent path exploitation
				if ($piece == "..") {
					die();
				}

				// We're done, everything is a command now.
				if ($ended) {
					$commands[] = $piece;
				// Keep looking for directories.
				} elseif (is_dir($inc_dir.$piece)) {
					$inc_file .= $piece."/";
					$inc_dir .= $piece."/";
				// File exists, we're ending now.
				} elseif ($piece != "_header" && $piece != "_footer" && file_exists($inc_file.$piece.".php")) {
					$inc_file .= $piece.".php";
					$ended = true;
					$found_file = true;
				// Couldn't find a file or directory.
				} else {
					$commands[] = $piece;
					$ended = true;
				}
			}

			if (!$found_file) {
				// If we have default in the routed directory, use it.
				if (file_exists($inc_dir."default.php")) {
					$inc_file = $inc_dir."default.php";
				// See if we can change the directory name into .php file in case the directory is empty but we have .php
				} elseif (file_exists(rtrim($inc_dir, "/").".php")) {
					$inc_file = rtrim($inc_dir, "/").".php";
				// We couldn't route anywhere apparently.
				} else {
					return array(false, false);
				}
			}

			return array($inc_file, $commands);
		}

		/*
			Function: getRoutedLayoutPartials
				Retrieves a list of route layout files (_header.php and _footer.php) for a given file path.

			Parameters:
				path - A file path

			Returns:
				An array containing an array of headers at the first index and footers at the second index.
		*/

		static function getRoutedLayoutPartials($path) {
			$file_location = ltrim(Text::replaceServerRoot($path), "/");
			$include_root = false;
			$pathed_includes = false;
			$headers = $footers = $pieces = array();

			// Get our path pieces and include roots setup properly
			if (strpos($file_location, "custom/admin/modules/") === 0) {
				$include_root = "admin/modules/";
				$pathed_includes = true;
				$pieces = explode("/", substr($file_location, 21));
			} elseif (strpos($file_location, "core/admin/modules/") === 0) {
				$include_root = "admin/modules/";
				$pathed_includes = true;
				$pieces = explode("/", substr($file_location, 19));
			} elseif (strpos($file_location, "custom/admin/ajax/")) {
				$include_root = "admin/ajax/";
				$pathed_includes = true;
				$pieces = explode("/", substr($file_location, 18));
			} elseif (strpos($file_location, "core/admin/ajax/") === 0) {
				$include_root = "admin/ajax/";
				$pathed_includes = true;
				$pieces = explode("/", substr($file_location, 16));
			} elseif (strpos($file_location, "templates/routed/") === 0) {
				$include_root = "templates/routed/";
				$pieces = explode("/", substr($file_location, 17));
			} elseif (strpos($file_location, "templates/ajax/") === 0) {
				$include_root = "templates/ajax/";
				$pieces = explode("/", substr($file_location, 15));
			} elseif (strpos($file_location, "extensions/") === 0) {
				$pieces = explode("/", $file_location);
				if ($pieces[2] == "templates" && ($pieces[3] == "routed" || $pieces[3] == "ajax")) {
					$include_root = "extensions/".$pieces[1]."/templates/".$pieces[3]."/";
					$pieces = array_slice($pieces, 4);
				} elseif ($pieces[2] == "modules") {
					$include_root = "extensions/".$pieces[1]."/modules/";
					$pieces = array_slice($pieces, 3);
				} elseif ($pieces[2] == "ajax") {
					$include_root = "extensions/".$pieces[1]."/ajax/";
					$pieces = array_slice($pieces, 3);
				}
			}

			// Only certain places include headers and footers
			if ($include_root) {
				$inc_path = "";
				foreach ($pieces as $piece) {
					if (substr($piece, -4, 4) != ".php") {
						$inc_path .= $piece."/";
						if ($pathed_includes) {
							$header = static::getIncludePath($include_root.$inc_path."_header.php");
							$footer = static::getIncludePath($include_root.$inc_path."_footer.php");
						} else {
							$header = SERVER_ROOT.$include_root.$inc_path."_header.php";
							$footer = SERVER_ROOT.$include_root.$inc_path."_footer.php";
						}
						if (file_exists($header)) {
							$headers[] = $header;
						}
						if (file_exists($footer)) {
							$footers[] = $footer;
						}
					}
				}
			}

			return array($headers, array_reverse($footers));
		}

		/*
			Function: redirect
				Simple URL redirect via header with proper code #
			
			Parameters:
				url - The URL to redirect to.
				code - The status code of redirect, defaults to normal 302 redirect.
		*/
		
		static function redirect($url, $codes = array("302")) {
			// If we're presently in the admin we don't want to allow the possibility of a redirect outside our site via malicious URLs
			if (defined("BIGTREE_ADMIN_ROUTED")) {
				$pieces = explode("/", $url);
				$bt_domain_pieces = explode("/", DOMAIN);
				if (strtolower($pieces[2]) != strtolower($bt_domain_pieces[2])) {
					return false;
				}
			}

			$status_codes = array(
				"200" => "OK",
				"300" => "Multiple Choices",
				"301" => "Moved Permanently",
				"302" => "Found",
				"304" => "Not Modified",
				"307" => "Temporary Redirect",
				"400" => "Bad Request",
				"401" => "Unauthorized",
				"403" => "Forbidden",
				"404" => "Not Found",
				"410" => "Gone",
				"500" => "Internal Server Error",
				"501" => "Not Implemented",
				"503" => "Service Unavailable",
				"550" => "Permission denied"
			);

			if (!is_array($codes)) {
				$codes = array($codes);
			}

			foreach ($codes as $code) {
				if ($status_codes[$code]) {
					header($_SERVER["SERVER_PROTOCOL"]." $code ".$status_codes[$code]);
				}
			}
			
			header("Location: $url");
			die();
		}

		/*
			Function: routeToPage
				Provides the page ID for a given path array.
				This is a method used by the router and the admin and can generally be ignored.
			
			Parameters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.
			
			Returns:
				An array containing [page ID, commands array, template routed status]
		*/
		
		static function routeToPage($path, $previewing = false) {
			$commands = array();
			$publish_at = $previewing ? "" : "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			
			// See if we have a straight up perfect match to the path.
			$page = SQL::fetch("SELECT bigtree_pages.id,bigtree_templates.routed
								FROM bigtree_pages LEFT JOIN bigtree_templates
								ON bigtree_pages.template = bigtree_templates.id
								WHERE path = ? AND archived = '' $publish_at", implode("/", $path));
			if ($page) {
				return array($page["id"], array(), $page["routed"]);
			}

			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;
			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path) - $x];
				$path_string = implode("/", array_slice($path, 0, -1 * $x));
				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$page_id = SQL::fetchSingle("SELECT bigtree_pages.id
											 FROM bigtree_pages JOIN bigtree_templates 
											 ON bigtree_pages.template = bigtree_templates.id 
											 WHERE bigtree_pages.path = ? AND 
												   bigtree_pages.archived = '' AND
												   bigtree_templates.routed = 'on' $publish_at", $path_string);
				if ($page_id) {
					return array($page_id, array_reverse($commands), "on");
				}
			}
			
			return array(false, false, false);
		}

	}
