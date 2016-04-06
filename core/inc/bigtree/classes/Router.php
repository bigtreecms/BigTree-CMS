<?php
	/*
		Class: BigTree\Router
			Provides an interface for handling BigTree routing.
	*/

	namespace BigTree;

	use BigTree;

	class Router {

		protected static $ReservedRoutes = array();

		static $Registry = false;
		static $Trunk = false;

		/*
			Function: checkPathHistory
				Checks the page route history table, redirects if the page is found.
			
			Parameters:
				path - An array of routes
		*/
		
		static function checkPathHistory($path) {
			$found = false;
			$x = count($path);

			while ($x) {
				$result = SQL::fetch("SELECT * FROM bigtree_route_history WHERE old_route = ?", implode("/",array_slice($path,0,$x)));
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
				$new_url = $new.substr($_GET["bigtree_htaccess_url"],strlen($old));
				static::redirect(WWW_ROOT.$new_url,"301");
			}
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
			list($admin_route) = explode("/",str_replace(WWW_ROOT,"",rtrim(ADMIN_ROOT,"/")));
			static::$ReservedRoutes[] = $admin_route;

			return static::$ReservedRoutes;
		}

		/*
		    Function: includeFile
				Includes a core file checking whether a custom override exists first.

			Parameter:
				file - File path (relative to /core/ or /custom/)
		*/

		static function includeFile($file) {
			$path = static::getIncludePath($file);
			
			if (file_exists($path)) {
				include_once $path;
			}
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
				$pieces = explode("/",$url);
				$bt_domain_pieces = explode("/",DOMAIN);
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
		
		static function routeToPage($path,$previewing = false) {
			$commands = array();
			$publish_at = $previewing ? "" : "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			
			// See if we have a straight up perfect match to the path.
			$page = SQL::fetch("SELECT bigtree_pages.id,bigtree_templates.routed
											FROM bigtree_pages LEFT JOIN bigtree_templates
											ON bigtree_pages.template = bigtree_templates.id
											WHERE path = ? AND archived = '' $publish_at", implode("/",$path));
			if ($page) {
				return array($page["id"],array(),$page["routed"]);
			}

			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;
			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path)-$x];
				$path_string = implode("/",array_slice($path,0,-1 * $x));
				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$page_id = SQL::fetchSingle("SELECT bigtree_pages.id
														 FROM bigtree_pages JOIN bigtree_templates 
														 ON bigtree_pages.template = bigtree_templates.id 
														 WHERE bigtree_pages.path = ? AND 
															   bigtree_pages.archived = '' AND
															   bigtree_templates.routed = 'on' $publish_at", $path_string);
				if ($page_id) {
					return array($page_id,array_reverse($commands),"on");
				}
			}
			
			return array(false,false,false);
		}

	}
