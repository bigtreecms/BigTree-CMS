<?php
	/*
		Class: BigTree\Router
			Provides an interface for handling BigTree routing.
	*/
	
	namespace BigTree;
	
	use BigTree;
	
	class Router
	{
		
		protected static $Booted = false;
		protected static $ReservedRoutes = [];
		
		/** @property BigTree\Page $CurrentPage */
		public static $BootError = null;
		public static $CurrentPage;
		public static $Config = [];
		public static $Content = "";
		public static $Debug = false;
		public static $Layout = "default";
		public static $Path = [];
		public static $POSTError = null;
		public static $Registry = false;
		public static $RouteParamNames = [];
		public static $RouteParamNamesPath = [];
		public static $Secure = false;
		public static $SiteRoots = [];
		
		/*
			Function: boot
				Builds caches from the database and configuration files.
		*/
		
		public static function boot($config, $path): void
		{
			if (static::$Booted) {
				return;
			}
			
			static::$Config = $config;
			static::$Debug = !empty($config["debug"]);
			static::$Path = $path;
			
			// Cleanup some config
			if (empty(static::$Config["trailing_slash_behavior"])) {
				static::$Config["trailing_slash_behavior"] = "";
			}
			
			if (empty(static::$Config["sites"]) || !is_array(static::$Config["sites"])) {
				static::$Config["sites"] = [];
			}

			// Check for POST errors
			if (defined("BIGTREE_PHP_BOOT_ERROR")) {
				$error = false;

				if (strpos(BIGTREE_PHP_BOOT_ERROR, "POST Content-Length") !== false) {
					$error = "post_max_size";
				}

				if (strpos(BIGTREE_PHP_BOOT_ERROR, "max_input_vars") !== false) {
					$error = "max_input_vars";
				}

				if ($error && $path[1] != "ajax") {
					$_SESSION["bigtree_admin"]["post_error"] = $error;
					static::redirect($_SERVER["HTTP_REFERER"]);
				} else {
					static::$POSTError = $error;
				}
			}
			
			$cache_file = SERVER_ROOT."cache/bigtree-module-cache.json";
			
			if ($config["debug"] || !file_exists($cache_file)) {
				// Preload the BigTreeModule class since others are based off it
				include_once Router::getIncludePath("inc/bigtree/modules.php");
				
				$data = [
					"routes" => ["admin" => [], "public" => [], "template" => []],
					"classes" => [],
					"extension_required_files" => []
				];
				
				// Get all modules from the db
				$modules = DB::getAll("modules");
				
				foreach ($modules as $module) {
					$class = $module["class"];
					$route = $module["route"];
					
					if ($class) {
						// Get the class file path
						if (strpos($route, "*") !== false) {
							list($extension, $file_route) = explode("*", $route);
							$path = "extensions/$extension/classes/$file_route.php";
						} else {
							$path = "custom/inc/modules/$route.php";
						}
						
						$data["classes"][$class] = $path;
						
						// Get the registered routes, load the class
						include_once SERVER_ROOT.$path;
						
						if (isset($class::$RouteRegistry) && is_array($class::$RouteRegistry)) {
							foreach ($class::$RouteRegistry as $registration) {
								$type = $registration["type"];
								unset($registration["type"]);
								
								$data["routes"][$type][] = $registration;
							}
						}
					}
				}
				
				// Get all extension required files and add them to a required list
				$extensions = DB::getAll("extensions");
				
				foreach ($extensions as $extension) {
					$id = $extension["id"];
					
					if (file_exists(SERVER_ROOT."extensions/$id/required/")) {
						$required_contents = FileSystem::getDirectoryContents(SERVER_ROOT."extensions/$id/required/");
						
						foreach (array_filter((array) $required_contents) as $file) {
							$data["extension_required_files"][] = $file;
						}
					}
				}
				
				if (!$config["debug"]) {
					// Cache it so we don't hit the database.
					FileSystem::createFile($cache_file, JSON::encode($data));
				}
			} else {
				$data = json_decode(file_get_contents($cache_file), true);
			}
			
			Module::$ClassCache = $data["classes"];
			Extension::$RequiredFiles = $data["extension_required_files"];
			static::$Registry = $data["routes"];
			
			// Find root paths for all sites to include in URLs if we're in a multi-site environment
			if (defined("BIGTREE_SITE_KEY") || (!empty($config["sites"]) && is_array($config["sites"]) && count($config["sites"]))) {
				$cache_location = SERVER_ROOT."cache/bigtree-multi-site-cache.json";
				
				if (!file_exists($cache_location)) {
					foreach ($config["sites"] as $site_key => $site_data) {
						$page = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".intval($site_data["trunk"])."'"));
						$site_data["key"] = $site_key;
						
						static::$SiteRoots[$page["path"]] = $site_data;
					}
					
					// We want the primary domain (at root 0) last as well as longer routes first
					ksort(static::$SiteRoots);
					static::$SiteRoots = array_reverse(static::$SiteRoots);
					
					file_put_contents($cache_location, BigTree::json(static::$SiteRoots));
				} else {
					static::$SiteRoots = json_decode(file_get_contents($cache_location), true);
				}
				
				foreach (static::$SiteRoots as $site_path => $site_data) {
					if ($site_data["trunk"] == BIGTREE_SITE_TRUNK) {
						define("BIGTREE_SITE_PATH", $site_path);
					}
				}
			}
			
			static::$Booted = true;
		}
		
		/*
			Function: checkPathHistory
				Checks the page route history table, redirects if the page is found.
			
			Parameters:
				path - An array of routes
		*/
		
		public static function checkPathHistory(array $path): void
		{
			// Add multi-site path
			if (defined("BIGTREE_SITE_PATH")) {
				$path = array_filter(array_merge(explode("/", BIGTREE_SITE_PATH), $path));
			}
			
			$route = false;
			$additional_commands = "";
			$x = count($path);
			
			while ($x) {
				$route = SQL::fetchSingle("SELECT new_route FROM bigtree_route_history
										   WHERE old_route = ?", implode("/", array_slice($path, 0, $x)));
				
				if ($route) {
					if ($x < count($path)) {
						$additional_commands = implode("/", array_slice($path, $x));
					}
					
					break;
				}
				
				$x--;
			}
			
			// If it's in the old routing table, send them to the new page.
			if ($route) {
				$page_id = SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE path = ?", $route);
				
				// If this page was moved multiple times, it could have more than one entry in the route history
				while ($route && !$page_id) {
					$route = SQL::fetchSingle("SELECT new_route FROM bigtree_route_history WHERE old_route = ?", $route);
					
					if ($route) {
						$page_id = SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE path = ?", $route);
					}
				}
				
				if ($page_id) {
					$redirect_url = Link::get($page_id);
					
					if ($additional_commands) {
						$redirect_url = rtrim($redirect_url, "/")."/".$additional_commands;
						
						if (static::$Config["trailing_slash_behavior"] != "remove") {
							$redirect_url .= "/";
						}
					}
					
					BigTree::redirect($redirect_url);
				}
			}
		}
		
		/*
			Function: clearCache
				Removes all page cache files in the cache directory.
		*/
		
		public static function clearCache(): void
		{
			$directory = opendir(SERVER_ROOT."cache/");
			
			while ($file = readdir($directory)) {
				if (substr($file, -5, 5) == ".page") {
					unlink(SERVER_ROOT."cache/".$file);
				}
			}
		}
		
		/*
			Function: getRemoteIP
				Returns the remote user's IP address (works with load balancers).

			Returns:
				An IP address
		*/
		
		public static function getRemoteIP(): ?string
		{
			if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
				return $_SERVER["HTTP_CLIENT_IP"];
			} elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				return $_SERVER["HTTP_X_FORWARDED_FOR"];
			} elseif (!empty($_SERVER["HTTP_X_FORWARDED"])) {
				return $_SERVER["HTTP_X_FORWARDED"];
			} elseif (!empty($_SERVER["HTTP_FORWARDED_FOR"])) {
				return $_SERVER["HTTP_FORWARDED_FOR"];
			} elseif (!empty($_SERVER["HTTP_FORWARDED"])) {
				return $_SERVER["HTTP_FORWARDED"];
			} elseif (!empty($_SERVER["HTTP_X_CLUSTER_CLIENT_IP"])) {
				return $_SERVER["HTTP_X_CLUSTER_CLIENT_IP"];
			} elseif (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
				return $_SERVER["HTTP_CF_CONNECTING_IP"];
			} elseif (!empty($_SERVER["REMOTE_ADDR"])) {
				return $_SERVER["REMOTE_ADDR"];
			}
			
			return null;
		}
		
		/*
			Function: forceHTTPS
				Forces the site into Secure mode to be served over HTTPS.
				When Secure mode is enabled, BigTree will enforce the user being at HTTPS and will rewrite all insecure resources (like CSS, JavaScript, and images) to use HTTPS.
		*/
		
		public static function forceHTTPS(): void
		{
			if (!static::getIsSSL()) {
				static::redirect("https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"], "301");
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
		
		public static function getIncludePath(string $file): string
		{
			if (file_exists(SERVER_ROOT."custom/".$file)) {
				return SERVER_ROOT."custom/".$file;
			} else {
				return SERVER_ROOT."core/".$file;
			}
		}
		
		/*
			Function: getIsSSL
				Returns whether BigTree believes it's being served over SSL or not.
		*/
		
		public static function getIsSSL(): bool
		{
			if (!empty($_SERVER["HTTPS"]) && $_SERVER['HTTPS'] !== "off") {
				return true;
			}
			
			if (!empty($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == 443) {
				return true;
			}
			
			if (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] == "https") {
				return true;
			}
			
			if (!empty($_SERVER["HTTP_X_FORWARDED_PORT"]) && $_SERVER["HTTP_X_FORWARDED_PORT"] == 443) {
				return true;
			}
			
			return false;
		}
		
		/*
			Function: getRegistryCommands
				Helper function for pattern based routing.
		*/
		
		public static function getRegistryCommands(string $path, string $pattern): ?array
		{
			// This method is based almost entirely on the Slim Framework's routing implementation (http://www.slimframework.com/)
			static::$RouteParamNames = [];
			static::$RouteParamNamesPath = [];
			
			// Convert URL params into regex patterns, construct a regex for this route, init params
			$regex_pattern = preg_replace_callback('#:([\w]+)\+?#', "BigTree\\Router::getRegistryCommandsCallback",
												   str_replace(')', ')?', $pattern));
			
			if (substr($pattern, -1) === '/') {
				$regex_pattern .= '?';
			}
			
			$regex = '#^'.$regex_pattern.'$#';
			
			// Do the regex match
			if (!preg_match($regex, $path, $values)) {
				return null;
			}
			
			$params = [];
			
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
		
		public static function getRegistryCommandsCallback(array $match)
		{
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
		
		public static function getReservedRoutes(): array
		{
			// Already cached them
			if (count(static::$ReservedRoutes)) {
				return static::$ReservedRoutes;
			}
			
			static::$ReservedRoutes = [
				"ajax",
				"css",
				"feeds",
				"js",
				"sitemap.xml",
				"_preview",
				"_preview-pending"
			];
			
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
		
		public static function getRoutedFileAndCommands(string $directory, array $path): array
		{
			$commands = [];
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
					return [false, []];
				}
			}
			
			return [$inc_file, $commands];
		}
		
		/*
			Function: getRoutedLayoutPartials
				Retrieves a list of route layout files (_header.php and _footer.php) for a given file path.

			Parameters:
				path - A file path

			Returns:
				An array containing an array of headers at the first index and footers at the second index.
		*/
		
		public static function getRoutedLayoutPartials(string $path): array
		{
			$file_location = ltrim(Text::replaceServerRoot($path), "/");
			$include_root = false;
			$pathed_includes = false;
			$headers = $footers = $pieces = [];
			
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
			
			return [$headers, array_reverse($footers)];
		}
		
		/*
			Function: redirect
				Simple URL redirect via header with proper code #
			
			Parameters:
				url - The URL to redirect to.
				code - The status code of redirect, defaults to normal 302 redirect.
		*/
		
		public static function redirect(string $url, string $code = "302"): void
		{
			// If we're presently in the admin we don't want to allow the possibility of a redirect outside our site via malicious URLs
			if (defined("BIGTREE_ADMIN_ROUTED")) {
				// Multiple redirect domains allowed
				if (count(static::$Config["sites"])) {
					$ok = false;
					$pieces = explode("/", $url);
					
					foreach (static::$Config["sites"] as $site_data) {
						$bt_domain_pieces = explode("/", $site_data["domain"]);
						
						if (strtolower($pieces[2]) == strtolower($bt_domain_pieces[2])) {
							$ok = true;
						}
					}
					
					if (!$ok) {
						return;
					}
				} else {
					$pieces = explode("/", $url);
					$bt_domain_pieces = explode("/", DOMAIN);
					
					if (strtolower($pieces[2]) != strtolower($bt_domain_pieces[2])) {
						return;
					}
				}
			}
			
			$status_codes = [
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
			];
			
			if ($status_codes[$code]) {
				header($_SERVER["SERVER_PROTOCOL"]." $code ".$status_codes[$code]);
			}
			
			header("Location: $url");
			die();
		}
		
		/*
			Function: redirectLower
				Redirects to the first visible child of the given page with a 301.
			
			Parameters:
				 page - A BigTree\Page object
		*/
		
		public static function redirectLower(Page $page): void
		{
			$path = SQL::fetchSingle("SELECT path FROM bigtree_pages 
						  			  WHERE in_nav = 'on' AND parent = ? 
						              ORDER BY position DESC, id ASC LIMIT 1", $page->ID);
			
			// Try for one that's not in nav
			if (!$path) {
				$path = SQL::fetchSingle("SELECT path FROM bigtree_pages 
						  		  		  WHERE in_nav != 'on' AND parent = ? 
						                  ORDER BY position DESC, id ASC LIMIT 1", $page->ID);
			}
			
			if ($path) {
				if (static::$Config["trailing_slash_behavior"] == "remove") {
					$url = WWW_ROOT.$path;
				} else {
					$url = WWW_ROOT.$path."/";
				}
				
				Router::redirect($url, "301");
			}
		}
		
		/*
			Function: routeToPage
				Provides the page ID for a given path array.
				This is a method used by the router and the admin and can generally be ignored.
			
			Parameters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.
			
			Returns:
				An array containing [page ID, commands array, template routed status, GET variables, URL hash]
		*/
		
		public static function routeToPage(array $path, bool $previewing = false): array
		{
			$commands = [];
			$publish_at = $previewing ? "" : "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			
			// Add multi-site path
			if (defined("BIGTREE_SITE_PATH")) {
				$path = array_filter(array_merge(explode("/", BIGTREE_SITE_PATH), $path));
			}
			
			// Get any GET variables and hashes and remove them
			$url_parse = parse_url(implode("/", array_values($path)));
			$query_vars = $url_parse["query"];
			$hash = $url_parse["fragment"];
			$path = explode("/", rtrim($url_parse["path"], "/"));
			
			// See if we have a straight up perfect match to the path.
			$page = SQL::fetch("SELECT bigtree_pages.id,bigtree_templates.routed
								FROM bigtree_pages LEFT JOIN bigtree_templates
								ON bigtree_pages.template = bigtree_templates.id
								WHERE path = ? AND archived = '' $publish_at", implode("/", $path));
			if ($page) {
				return [$page["id"], [], $page["routed"], $query_vars, $hash];
			}
			
			// Resetting $path to ensure it's numerically indexed, chop off the end until we find a page
			$x = 0;
			$path = array_values($path);
			
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
					return [$page_id, array_reverse($commands), "on", $query_vars, $hash];
				}
			}
			
			return [false, false, false, [], false];
		}
		
	}
