<?php
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	class BigTreeCMSBase {
	
		var $AutoSaveSettings = array();

		static $BreadcrumbTrunk;
		static $IRLCache = array();
		static $IPLCache = array();
		static $MySQLTime = false;
		static $ReplaceableRootKeys = array();
		static $ReplaceableRootVals = array();
		static $Secure;
		static $SiteRoots = array();

		/*
			Constructor:
				Builds a flat file module class list so that module classes can be autoloaded instead of always in memory.
		*/
		
		function __construct() {
			global $bigtree;
			
			// If the cache exists, just use it.
			if (file_exists(SERVER_ROOT."cache/bigtree-module-class-list.json")) {
				$items = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-module-class-list.json"),true);
			} else {
				// Get the Module Class List
				$q = sqlquery("SELECT * FROM bigtree_modules");
				$items = array();
				while ($f = sqlfetch($q)) {
					$items[$f["class"]] = $f["route"];
				}
				
				// Cache it so we don't hit the database.
				BigTree::putFile(SERVER_ROOT."cache/bigtree-module-class-list.json",BigTree::json($items));
			}
			
			$this->ModuleClassList = $items;
			
			// Find root paths for all sites to include in URLs if we're in a multi-site environment
			if (defined("BIGTREE_SITE_KEY") || (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]))) {
				$cache_location = SERVER_ROOT."cache/multi-site-cache.json";

				if (!file_exists($cache_location)) {
					foreach ($bigtree["config"]["sites"] as $site_key => $site_data) {
						$page = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".intval($site_data["trunk"])."'"));
						
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
		}

		/*
			Destructor:
				Saves settings back to the database that were instantiated through the autoSaveSetting method.
		*/

		function __destruct() {
			foreach ($this->AutoSaveSettings as $id => $obj) {
				if (is_object($obj)) {
					BigTreeAdmin::updateSettingValue($id,get_object_vars($obj));
				} else {
					BigTreeAdmin::updateSettingValue($id,$obj);
				}
			}
		}

		/*
			Function: autoSaveSetting
				Returns a reference to an object that can be modified which will automatically save back to a bigtree_settings entry on the $cms class destruction.
				The entry in bigtree_settings should be an associate array. If the setting doesn't exist, an encrypted setting with the passed in id will be created.
				You MUST set your variable to be a reference using $var = &$cms->autoSaveSetting("my-id") for this to function properly.

			Parameters:
				id - The bigtree_settings id.
				return_object - Return the data an object (default, set to false to return as array)

			Returns:
				An object reference.
		*/

		function &autoSaveSetting($id,$return_object = true) {
			$id = static::extensionSettingCheck($id);

			// Only want one usage to exist
			if (!isset($this->AutoSaveSettings[$id])) {
				$data = $this->getSetting($id);

				// Create a setting if it doesn't exist yet
				if ($data === false) {
					// If an extension is creating an auto save setting, make it a reference back to the extension
					if (defined("EXTENSION_ROOT") && strpos($id,"bigtree-internal-") !== 0) {
						$extension = sqlescape(rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/"));
						
						// Don't append extension again if it's already being called via the namespace
						if (strpos($id,"$extension*") === false) {
							$id = "$extension*$id";
						}
						
						sqlquery("INSERT INTO bigtree_settings (`id`,`encrypted`,`system`,`extension`) VALUES ('".sqlescape($id)."','on','on','$extension')");
					} else {
						sqlquery("INSERT INTO bigtree_settings (`id`,`encrypted`,`system`) VALUES ('".sqlescape($id)."','on','on')");
					}
					$data = array();
				}

				// Asking for an object? Return it as an object
				if ($return_object) {
					$obj = new stdClass;
					if (is_array($data)) {
						foreach ($data as $key => $val) {
							$obj->$key = $val;
						}
					}
					$this->AutoSaveSettings[$id] = $obj;
				// Otherwise return an array
				} else {
					$this->AutoSaveSettings[$id] = $data;
				}
			}

			// Already exists, return it
			return $this->AutoSaveSettings[$id];
		}

		/*
			Function: cacheDelete
				Deletes data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data (if no key is passed, deletes all data for a given identifier)
		*/

		static function cacheDelete($identifier,$key = false) {
			$identifier = sqlescape($identifier);
			
			if ($key === false) {
				sqlquery("DELETE FROM bigtree_caches WHERE `identifier` = '$identifier'");
			} else {
				sqlquery("DELETE FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '".sqlescape($key)."'");
			}
		}

		/*
			Function: cacheGet
				Retrieves data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data.
				max_age - The maximum age (in seconds) for the data, defaults to any age.
				decode - Decode JSON (defaults to true, specify false to return JSON)

			Returns:
				Data from the table (json decoded, objects convert to keyed arrays) if it exists or false.
		*/

		static function cacheGet($identifier,$key,$max_age = false,$decode = true) {
			$identifier = sqlescape($identifier);
			$key = sqlescape($key);

			if ($max_age) {
				// We need to get MySQL's idea of what time it is so that if PHP's differs we don't screw up caches.
				if (!static::$MySQLTime) {
					$t = sqlfetch(sqlquery("SELECT NOW() as `time`"));
					static::$MySQLTime = $t["time"];
				}
				$max_age = date("Y-m-d H:i:s",strtotime(static::$MySQLTime) - $max_age);

				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key' AND timestamp >= '$max_age'"));
			} else {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key'"));
			}

			if (!$f) {
				return false;
			}
			
			if ($decode) {
				return json_decode($f["value"],true);
			} else {
				return $f["value"];
			}
		}

		/*
			Function: cachePut
				Puts data into BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data.
				value - The data to store.
				replace - Whether to replace an existing value (defaults to true).

			Returns:
				True if successful, false if the indentifier/key combination already exists and replace was set to false.
		*/

		static function cachePut($identifier,$key,$value,$replace = true) {
			$identifier = sqlescape($identifier);
			$key = sqlescape($key);
			$f = sqlfetch(sqlquery("SELECT `key` FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key'"));
			if ($f && !$replace) {
				return false;
			}

			$value = BigTree::json($value,true);
			
			if ($f) {
				sqlquery("UPDATE bigtree_caches SET `value` = '$value', `timestamp` = NOW() WHERE `identifier` = '$identifier' AND `key` = '$key'");
			} else {
				sqlquery("INSERT INTO bigtree_caches (`identifier`,`key`,`value`) VALUES ('$identifier','$key','$value')");
			}
			return true;
		}

		/*
			Function: cacheUnique
				Puts data into BigTree's cache table with a random unqiue key and returns the key.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				value - The data to store

			Returns:
				They unique cache key.
		*/

		static function cacheUnique($identifier,$value) {
			$success = false;
			while (!$success) {
				$key = uniqid("",true);
				$success = static::cachePut($identifier,$key,$value,false);
			}
			return $key;
		}
		
		/*
			Function: catch404
				Manually catch and display the 404 page from a routed template; logs missing page with handle404
		*/
		
		static function catch404() {
			global $admin,$bigtree,$cms;
			
			ob_clean();
			if (static::handle404(str_ireplace(WWW_ROOT,"",BigTree::currentURL()))) {
				$bigtree["layout"] = "default";
				ob_start();
				include SERVER_ROOT."templates/basic/_404.php";
				$bigtree["content"] = ob_get_clean();
				ob_start();
				include "../templates/layouts/".$bigtree["layout"].".php";
				die();
			}
		}
		
		/*
			Function: checkOldRoutes
				Checks the old route table, redirects if the page is found.
			
			Parameters:
				path - An array of routes
		*/
		
		static function checkOldRoutes($path) {
			$found = false;
			$x = count($path);
			while ($x) {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_route_history WHERE old_route = '".sqlescape(implode("/",array_slice($path,0,$x)))."'"));
				if ($f) {
					$old = $f["old_route"];
					$new = $f["new_route"];
					$found = true;
					break;
				}
				$x--;
			}
			// If it's in the old routing table, send them to the new page.
			if ($found) {
				$new_url = $new.substr($_GET["bigtree_htaccess_url"],strlen($old));
				BigTree::redirect(WWW_ROOT.$new_url,"301");
			}
		}
		
		/*
			Function: decodeResources
				Turns the JSON resources data into a PHP array of resources with links being translated into front-end readable links.
				This function is called by BigTree's router and is generally not a function needed to end users.
			
			Parameters:
				data - JSON encoded callout data.
			
			Returns:
				An array of resources.
		*/

		static function decodeResources($data) {
			if (!is_array($data)) {
				$data = json_decode($data,true);
			}
			if (is_array($data)) {
				foreach ($data as $key => $val) {
					if (is_array($val)) {
						// If this value is an array, untranslate it so that {wwwroot} and ipls get fixed.
						$val = BigTree::untranslateArray($val);
					} elseif (is_array(json_decode($val,true))) {
						// If this value is an array, untranslate it so that {wwwroot} and ipls get fixed.
						$val = BigTree::untranslateArray(json_decode($val,true));
					} else {
						// Otherwise it's a string, just replace the {wwwroot} and ipls.
						$val = static::replaceInternalPageLinks($val);				
					}
					$data[$key] = $val;
				}
			}
			return $data;
		}
		
		/*
			Function: drawXMLSitemap
				Outputs an XML sitemap.
		*/
		
		static function drawXMLSitemap() {
			header("Content-type: text/xml");
			echo '<?xml version="1.0" encoding="UTF-8" ?>';
			echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
			$q = sqlquery("SELECT id,template,external,path FROM bigtree_pages WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");

			while ($f = sqlfetch($q)) {
				if ($f["template"] || strpos($f["external"],DOMAIN)) {	
					if (!$f["template"]) {
						$link = static::getInternalPageLink($f["external"]);
					} else {
						$link = static::linkForPath($f["path"]);
					}
					
					echo "<url><loc>".$link."</loc></url>\n";
					
					// Added routed template support
					$tf = sqlfetch(sqlquery("SELECT bigtree_modules.class AS module_class FROM bigtree_templates JOIN bigtree_modules ON bigtree_modules.id = bigtree_templates.module WHERE bigtree_templates.id = '".$f["template"]."'"));
					
					if ($tf["module_class"]) {
						$mod = new $tf["module_class"];
						
						if (method_exists($mod,"getSitemap")) {
							$subnav = $mod->getSitemap($f);
							
							foreach ($subnav as $s) {
								echo "<url><loc>".$s["link"]."</loc></url>\n";
							}
						}
						
						$mod = $subnav = null;
					}
				}
			}
			echo '</urlset>';
			die();
		}

		/*
			Function: extensionSettingCheck
				Checks to see if we're in an extension and if we're requesting a setting attached to it.
				For example, if "test-setting" is requested and "com.test.extension*test-setting" exists it will be used.

			Parameters:
				id - Setting id

			Returns:
				An extension setting ID if one is found.
		*/

		static function extensionSettingCheck($id) {
			global $bigtree;

			$id = sqlescape($id);

			// See if we're in an extension
			if (!empty($bigtree["extension_context"])) {
				$extension = $bigtree["extension_context"];

				// If we're already asking for it by it's namespaced name, don't append again.
				if (substr($id,0,strlen($extension)) == $extension) {
					return $id;
				}

				// See if namespaced version exists
				$f = sqlfetch(sqlquery("SELECT id FROM bigtree_settings WHERE id = '$extension*$id'"));

				if ($f) {
					return "$extension*$id";
				}
			}

			return $id;
		}
		
		/*
		    Function: generateReplaceableRoots
				Caches a list of tokens and the values that are related to them.
		*/
		
		static function generateReplaceableRoots() {
			global $bigtree;
			
			$valid_root = function($root) {
				return (substr($root, 0, 7) == "http://" || substr($root, 0, 8) == "https://" || substr($root, 0, 2) == "//");
			};
			
			// Figure out what roots we can replace
			if (!count(static::$ReplaceableRootKeys)) {
				if ($valid_root(ADMIN_ROOT)) {
					static::$ReplaceableRootKeys[] = ADMIN_ROOT;
					static::$ReplaceableRootVals[] = "{adminroot}";
				}
				
				if (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
					foreach ($bigtree["config"]["sites"] as $site_key => $site_configuration) {
						if ($valid_root($site_configuration["static_root"])) {
							static::$ReplaceableRootKeys[] = $site_configuration["static_root"];
							static::$ReplaceableRootVals[] = "{staticroot:$site_key}";
						}
						
						if ($valid_root($site_configuration["www_root"])) {
							static::$ReplaceableRootKeys[] = $site_configuration["www_root"];
							static::$ReplaceableRootVals[] = "{wwwroot:$site_key}";
						}
					}
				}
				
				if ($valid_root(STATIC_ROOT)) {
					static::$ReplaceableRootKeys[] = STATIC_ROOT;
					static::$ReplaceableRootVals[] = "{staticroot}";
				}
				
				if ($valid_root(WWW_ROOT)) {
					static::$ReplaceableRootKeys[] = WWW_ROOT;
					static::$ReplaceableRootVals[] = "{wwwroot}";
				}
			}
		}
		
		/*
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for pages above the current page.
			
			Parameters:
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumbByPage>
		*/
		
		static function getBreadcrumb($ignore_trunk = false) {
			global $bigtree;
			return static::getBreadcrumbByPage($bigtree["page"],$ignore_trunk);
		}
		
		/*
			Function: getBreadcrumbByPage
				Returns an array of titles, links, and ids for the pages above the given page.
			
			Parameters:
				page - A page array (containing at least the "path" from the database) *(optional)*
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
				If a trunk is hit, BigTreeCMS::$BreadcrumbTrunk is set to the trunk.
			
			See Also:
				<getBreadcrumb>
		*/
		
		static function getBreadcrumbByPage($page,$ignore_trunk = false) {
			global $bigtree;
			
			// Break up the pieces so we can get each piece of the path individually and pull all the pages above this one.
			$bc = array();
			$pieces = explode("/", $page["path"]);
			$paths = array();
			$path = "";
			
			foreach ($pieces as $piece) {
				$path = $path.$piece."/";
				$paths[] = "path = '".sqlescape(trim($path,"/"))."'";
			}
			
			// Get all the ancestors, ordered by the page length so we get the latest first and can count backwards to the trunk.
			$q = sqlquery("SELECT id,nav_title,path,trunk FROM bigtree_pages WHERE (".implode(" OR ",$paths).") ORDER BY LENGTH(path) DESC");
			$trunk_hit = false;
			while ($f = sqlfetch($q)) {
				// In case we want to know what the trunk is.
				if ($f["trunk"] || $f["id"] == BIGTREE_SITE_TRUNK) {
					$trunk_hit = true;
					static::$BreadcrumbTrunk = $f;
				}
				
				if (!$trunk_hit || $ignore_trunk) {
					$bc[] = array(
						"title" => stripslashes($f["nav_title"]),
						"link" => static::linkForPath($f["path"]),
						"id" => $f["id"]
					);
				}
			}
			
			$bc = array_reverse($bc);
			
			// Check for module breadcrumbs
			$mod = sqlfetch(sqlquery("SELECT bigtree_modules.class FROM bigtree_modules JOIN bigtree_templates ON bigtree_modules.id = bigtree_templates.module WHERE bigtree_templates.id = '".$page["template"]."'"));
			if ($mod["class"]) {
				if (class_exists($mod["class"])) {
					$module = new $mod["class"];
					if (method_exists($module, "getBreadcrumb")) {
						$bc = array_merge($bc,$module->getBreadcrumb($page));
					}
				}
			}
			
			return $bc;
		}
		
		/*
			Function: getFeed
				Gets a feed's information from the database.
			
			Parameters:
				item - Either the ID of the feed to pull or a raw database row of the feed data
			
			Returns:
				An array of feed information with options and fields decoded from JSON.
				
			See Also:
				<getFeedByRoute>
		*/
		
		static function getFeed($item) {
			if (!is_array($item)) {
				$item = sqlescape($item);
				$item = sqlfetch(sqlquery("SELECT * FROM bigtree_feeds WHERE id = '$item'"));
			}
			if (!$item) {
				return false;
			}
			$item["options"] = json_decode($item["options"],true);
			if (is_array($item["options"])) {
				foreach ($item["options"] as &$option) {
					$option = static::replaceRelativeRoots($option);
				}
			}
			$item["fields"] = json_decode($item["fields"],true);
			return $item;
		}
		
		/*
			Function: getFeedByRoute
				Gets a feed's information from the database
			
			Parameters:
				route - The route of the feed to pull.
			
			Returns:
				An array of feed information with options and fields decoded from JSON.
			
			See Also:
				<getFeed>
		*/
		
		static function getFeedByRoute($route) {
			$route = sqlescape($route);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_feeds WHERE route = '$route'"));
			return static::getFeed($item);
		}
		
		/*
			Function: getHiddenNavByParent
				Returns an alphabetical list of pages that are not visible in navigation.
			
			Parameters:
				parent - The parent ID for which to pull child pages.
			
			Returns:
				An array of page entries from the database (without resources or callouts).
				
			See Also:
				<getNavByParent>
		*/
		
		static function getHiddenNavByParent($parent = 0) {
			return static::getNavByParent($parent,1,false,true);
		}
		
		/*
			Function: getInternalPageLink
				Returns a hard link to the page's publicly accessible URL from its encoded soft link URL.
			
			Parameters:
				ipl - Internal Page Link (ipl://, irl://, {wwwroot}, or regular URL encoding)
			
			Returns:
				Public facing URL.
		*/
		
		static function getInternalPageLink($ipl) {
			global $bigtree;

			// Regular links
			if (substr($ipl,0,6) != "ipl://" && substr($ipl,0,6) != "irl://") {
				return static::replaceRelativeRoots($ipl);
			}

			$ipl = explode("//",$ipl);
			$navid = $ipl[1];

			// Resource Links
			if ($ipl[0] == "irl:") {
				// See if it's in the cache.
				if (isset(static::$IRLCache[$navid])) {
					if ($ipl[2]) {
						return BigTree::prefixFile(static::$IRLCache[$navid],$ipl[2]);
					} else {
						return static::$IRLCache[$navid];
					}
				} else {
					$r = sqlfetch(sqlquery("SELECT * FROM bigtree_resources WHERE id = '".sqlescape($navid)."'"));
					$file = $r ? static::replaceRelativeRoots($r["file"]) : false;
					static::$IRLCache[$navid] = $file;

					if ($ipl[2]) {
						return BigTree::prefixFile($file,$ipl[2]);
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
				$commands = implode("/",$c);

				// If the URL's last piece has a GET (?), hash (#), or appears to be a file (.) don't add a trailing slash
				if ($bigtree["config"]["trailing_slash_behavior"] != "remove" && strpos($last,"#") === false && strpos($last,"?") === false && strpos($last,".") === false) {
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
				$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".sqlescape($navid)."'"));

				// Set the cache
				static::$IPLCache[$navid] = rtrim(static::linkForPath($f["path"]), "/");

				if ($bigtree["config"]["trailing_slash_behavior"] != "remove" || $commands != "") {
					return static::$IPLCache[$navid]."/".$commands;
				} else {
					return static::$IPLCache[$navid];
				}
			}
		}
		
		/*
			Function: getLink
				Returns the public link to a page in the database.
			
			Parameters:
				id - The ID of the page.
			
			Returns:
				Public facing URL.
		*/
		
		static function getLink($id) {
			global $bigtree;
			
			// Homepage, just return the web root.
			if ($id == BIGTREE_SITE_TRUNK) {
				return WWW_ROOT;
			}

			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				return static::linkForPath($bigtree["page"]["path"]);
			} else {
				// Otherwise we'll grab the page path from the db.
				$page = sqlfetch(sqlquery("SELECT path, template, external FROM bigtree_pages WHERE id = '".sqlescape($id)."' AND archived != 'on'"));
				
				if ($page) {
					if ($page["external"] !== "" && $page["template"] === "") {
						if (substr($page["external"], 0, 6) == "ipl://" || substr($page["external"], 0, 6) == "irl://") {
							$page["external"] = static::getInternalPageLink($page["external"]);
						}
						
						return $page["external"];
					}

					return static::linkForPath($page["path"]);
				}
			}
			
			return false;
		}
		
		/*
			Function: getNavByParent
				Returns a multi-level navigation array of pages visible in navigation
				(or hidden, if $only_hidden is set to true)
			
			Parameters:
				parent - Either a single page ID or an array of page IDs -- the latter is used internally
				levels - The number of levels of navigation depth to recurse
				follow_module - Whether to pull module navigation or not
				only_hidden - Whether to pull visible (false) or hidden (true) pages
				explicit_zero - In a multi-site environment you must pass true for this parameter if you want root level children rather than the site-root level
			
			Returns:
				A multi-level navigation array containing "id", "parent", "title", "route", "link", "new_window", and "children"
		*/
			
		static function getNavByParent($parent = 0, $levels = 1, $follow_module = true, $only_hidden = false, $explicit_zero = false) {
			global $bigtree;
			static $module_nav_count = 0;
			
			$nav = array();
			$find_children = array();
			
			// If we're asking for root (0) and in multi-site, use that site's root instead of the top-level root
			if (!$explicit_zero && $parent === 0 && BIGTREE_SITE_TRUNK !== 0) {
				$parent = BIGTREE_SITE_TRUNK;
			}
			
			// If the parent is an array, this is actually a recursed call.
			// We're finding all the children of all the parents at once -- then we'll assign them back to the proper parent instead of doing separate calls for each.
			if (is_array($parent)) {
				$where_parent = array();
				foreach ($parent as $p) {
					$where_parent[] = "parent = '".sqlescape($p)."'";
				}
				$where_parent = "(".implode(" OR ",$where_parent).")";
			// If it's an integer, let's just pull the children for the provided parent.
			} else {
				$parent = sqlescape($parent);
				$where_parent = "parent = '$parent'";
			}
			
			$in_nav = $only_hidden ? "" : "on";
			$sort = $only_hidden ? "nav_title ASC" : "position DESC, id ASC";
			
			$q = sqlquery("SELECT id,nav_title,parent,external,new_window,template,route,path FROM bigtree_pages WHERE $where_parent AND in_nav = '$in_nav' AND archived != 'on' AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL) ORDER BY $sort");
			
			// Wrangle up some kids
			while ($f = sqlfetch($q)) {
				$link = static::linkForPath($f["path"]);
				$new_window = false;
				
				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.  Then we'll see if we should open this thing in a new window.
				if ($f["external"] && $f["template"] == "") {
					$link = static::getInternalPageLink($f["external"]);
					if ($f["new_window"] == "Yes") {
						$new_window = true;
					}
				}
				
				// Add it to the nav array
				$nav[$f["id"]] = array("id" => $f["id"], "parent" => $f["parent"], "title" => $f["nav_title"], "route" => $f["route"], "link" => $link, "new_window" => $new_window, "children" => array());
				
				// If we're going any deeper, mark down that we're looking for kids of this kid.
				if ($levels > 1) {
					$find_children[] = $f["id"];
				}
			}
			
			// If we're looking for children, send them all back into getNavByParent, decrease the depth we're looking for by one.
			if (count($find_children)) {
				$subnav = static::getNavByParent($find_children,$levels - 1,$follow_module);
				foreach ($subnav as $item) {
					// Reassign these new children back to their parent node.
					$nav[$item["parent"]]["children"][$item["id"]] = $item;
				}
			}
			
			// If we're pulling in module navigation...
			if ($follow_module) {
				// This is a recursed iteration.
				if (is_array($parent)) {
					$where_parent = array();
					foreach ($parent as $p) {
						$where_parent[] = "bigtree_pages.id = '".sqlescape($p)."'";
					}
					$q = sqlquery("SELECT bigtree_modules.class,bigtree_templates.routed,bigtree_templates.module,bigtree_pages.id,bigtree_pages.path,bigtree_pages.template FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_modules.id = bigtree_templates.module AND (".implode(" OR ",$where_parent).")");
					while ($f = sqlfetch($q)) {
						// If the class exists, instantiate it and call it
						if ($f["class"] && class_exists($f["class"])) {
							$module = new $f["class"];
							if (method_exists($module,"getNav")) {
								$modNav = $module->getNav($f);
								// Give the parent back to each of the items it returned so they can be reassigned to the proper parent.
								$module_nav = array();
								foreach ($modNav as $item) {
									$item["parent"] = $f["id"];
									$item["id"] = "module_nav_".$module_nav_count;
									$module_nav[] = $item;
									$module_nav_count++;
								}
								if ($module->NavPosition == "top") {
									$nav = array_merge($module_nav,$nav);
								} else {
									$nav = array_merge($nav,$module_nav);
								}
							}
						}
					}
				// This is the first iteration.
				} else {
					$f = sqlfetch(sqlquery("SELECT bigtree_modules.class,bigtree_templates.routed,bigtree_templates.module,bigtree_pages.id,bigtree_pages.path,bigtree_pages.template FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_modules.id = bigtree_templates.module AND bigtree_pages.id = '$parent'"));
					// If the class exists, instantiate it and call it.
					if ($f["class"] && class_exists($f["class"])) {
						$module = new $f["class"];
						if (method_exists($module,"getNav")) {
							if ($module->NavPosition == "top") {
								$nav = array_merge($module->getNav($f),$nav);
							} else {
								$nav = array_merge($nav,$module->getNav($f));
							}
						}
					}
				}
			}
			
			return $nav;
		}
		
		/*
			Function: getNavId
				Provides the page ID for a given path array.
				This is a method used by the router and the admin and can generally be ignored.
			
			Parameters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.
			
			Returns:
				An array containing the page ID and any additional commands.
		*/
		
		static function getNavId($path,$previewing = false) {
			$commands = array();
			
			// Add multi-site path
			if (defined("BIGTREE_SITE_PATH")) {
				$path = array_filter(array_merge(explode("/", BIGTREE_SITE_PATH), $path));
			}

			// Reset indexes
			$path = array_values($path);
			
			if (!$previewing) {
				$publish_at = "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			} else {
				$publish_at = "";
			}
			
			// See if we have a straight up perfect match to the path.
			$spath = sqlescape(implode("/",$path));
			$f = sqlfetch(sqlquery("SELECT bigtree_pages.id,bigtree_templates.routed FROM bigtree_pages LEFT JOIN bigtree_templates ON bigtree_pages.template = bigtree_templates.id WHERE path = '$spath' AND archived = '' $publish_at"));
			if ($f) {
				return array($f["id"],$commands,$f["routed"]);
			}
			
			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;
			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path)-$x];
				$spath = sqlescape(implode("/",array_slice($path,0,-1 * $x)));
				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$f = sqlfetch(sqlquery("SELECT bigtree_pages.id FROM bigtree_pages JOIN bigtree_templates ON bigtree_pages.template = bigtree_templates.id WHERE bigtree_pages.path = '$spath' AND bigtree_pages.archived = '' AND bigtree_templates.routed = 'on' $publish_at"));
				if ($f) {
					return array($f["id"],array_reverse($commands),"on");
				}
			}
			
			return array(false,false,false);
		}
		
		/*
			Function: getPage
				Returns a page along with its resources and callouts decoded.
			
			Parameters:
				id - The ID of the page.
				decode - Whether to decode resources and callouts or not (setting to false saves processing time)
			
			Returns:
				A page array from the database.
		*/
		
		static function getPage($id,$decode = true) {
			$id = sqlescape($id);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$id'"));
			if (!$f) {
				return false;
			}
			if ($f["external"] && $f["template"] == "") {
				$f["external"] = static::getInternalPageLink($f["external"]);
			}
			if ($decode) {
				$f["resources"] = static::decodeResources($f["resources"]);
				// Backwards compatibility with 4.0 callout system
				if (isset($f["resources"]["4.0-callouts"])) {
					$f["callouts"] = $f["resources"]["4.0-callouts"];
				} elseif (isset($f["resources"]["callouts"])) {
					$f["callouts"] = $f["resources"]["callouts"];
				} else {
					$f["callouts"] = array();
				}
			}
			return $f;
		}
		
		/*
			Function: getPendingPage
				Returns a page along with pending changes applied.
			
			Parameters:
				id - The ID of the page.
				decode - Whether to decode resources and callouts or not (setting to false saves processing time, defaults true).
				return_tags - Whether to return tags for the page (defaults false).
			
			Returns:
				A page array from the database.
		*/
		
		static function getPendingPage($id,$decode = true,$return_tags = false) {
			// Numeric id means the page is live.
			if (is_numeric($id)) {
				$page = static::getPage($id);
				if (!$page) {
					return false;
				}
				// If we're looking for tags, apply them to the page.
				if ($return_tags) {
					$page["tags"] = static::getTagsForPage($id);
				}
				// Get pending changes for this page.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '".$page["id"]."'"));

			// If it's prefixed with a "p" then it's a pending entry.
			} else {
				// Set the page to empty, we're going to loop through the change later and apply the fields.
				$page = array();
				// Get the changes.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `id` = '".sqlescape(substr($id,1))."'"));
				if ($f) {
					$f["id"] = $id;
				} else {
					return false;
				}
			}

			// If we have changes, apply them.
			if ($f) {
				$page["changes_applied"] = true;
				$page["updated_at"] = $f["date"];
				$changes = json_decode($f["changes"],true);
				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = static::getInternalPageLink($val);
					}
					$page[$key] = $val;
				}
				if ($return_tags) {
					// Decode the tag changes, apply them back.
					$tags = array();
					$tags_changes = json_decode($f["tags_changes"],true);
					if (is_array($tags_changes)) {
						foreach ($tags_changes as $tag) {
							$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$tag'"));
						}
					}
					$page["tags"] = $tags;
				}
			}
			
			// Turn resource entities into arrays that have been IPL decoded.
			if ($decode) {
				if (isset($page["resources"]) && is_array($page["resources"])) {
					$page["resources"] = static::decodeResources($page["resources"]);	
				}

				// Backwards compatibility with 4.0 callout system
				if (isset($page["resources"]["4.0-callouts"])) {
					$page["callouts"] = $page["resources"]["4.0-callouts"];
				} elseif (isset($page["resources"]["callouts"])) {
					$page["callouts"] = $page["resources"]["callouts"];
				} else {
					$page["callouts"] = array();
				}
			}

			return $page;
		}
		
		/*
			Function: getPreviewLink
				Returns a URL to where this page can be previewed.
			
			Parameters:
				id - The ID of the page (or pending page)
			
			Returns:
				A URL.
		*/
		
		static function getPreviewLink($id) {
			if (substr($id,0,1) == "p") {
				return WWW_ROOT."_preview-pending/$id/";
			} elseif ($id == 0) {
				return WWW_ROOT."_preview/";
			} else {
				$link = static::getLink($id);

				return str_replace(WWW_ROOT, WWW_ROOT."_preview/", $link);
			}
		}
		
		/*
			Function: getRelatedPagesByTags
				Returns pages related to the given set of tags.
			
			Parameters:
				tags - An array of tags to search for.
			
			Returns:
				An array of related pages sorted by relevance (how many tags get matched).
		*/
		
		static function getRelatedPagesByTags($tags = array()) {
			$results = array();
			$relevance = array();
			foreach ($tags as $tag) {
				if (is_array($tag)) {
					$tag = $tag["tag"];
				}
				$tdat = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE tag = '".sqlescape($tag)."'"));
				if ($tdat) {
					$q = sqlquery("SELECT * FROM bigtree_tags_rel WHERE tag = '".$tdat["id"]."' AND `table` = 'bigtree_pages'");
					while ($f = sqlfetch($q)) {
						$id = $f["entry"];
						if (in_array($id,$results)) {
							$relevance[$id]++;
						} else {
							$results[] = $id;
							$relevance[$id] = 1;
						}
					}
				}
			}
			array_multisort($relevance,SORT_DESC,$results);
			$items = array();
			foreach ($results as $result) {
				$items[] = static::getPage($result);
			}
			return $items;
		}
		
		/*
			Function: getSetting
				Gets the value of a setting.
			
			Parameters:
				id - The ID of the setting.
			
			Returns:				
				A string or array of the setting's value.
		*/
		
		static function getSetting($id) {
			global $bigtree;
			$id = static::extensionSettingCheck($id);
			$setting = sqlfetch(sqlquery("SELECT * FROM bigtree_settings WHERE id = '$id'"));
			// Setting doesn't exist
			if (!$setting) {
				return false;
			}

			// If the setting is encrypted, we need to re-pull just the value.
			if ($setting["encrypted"]) {
				$setting = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".sqlescape($bigtree["config"]["settings_key"])."') AS `value`, system FROM bigtree_settings WHERE id = '$id'"));
			}

			$value = json_decode($setting["value"],true);
			if (is_array($value)) {
				return BigTree::untranslateArray($value);
			} else {
				return static::replaceInternalPageLinks($value);
			}
		}
		
		
		/*
			Function: getSettings
				Gets the value of multiple settings.
			
			Parameters:
				id - Array containing the ID of the settings.
			
			Returns:				
				Array containing the string or array of each setting's value.
		*/
		
		static function getSettings($ids) {
			global $bigtree;

			// If for some reason we only requested one, just call getSetting
			if (!is_array($ids)) {
				return array(static::getSetting($ids));
			}

			// If we're in an extension, just call getSetting on the whole array since we need to make inferences on each ID
			if (defined("EXTENSION_ROOT")) {
				$settings = array();
				foreach ($ids as $id) {
					$settings[$id] = static::getSetting($id);
				}
				return $settings;
			}

			// Not in an extension, we can query them all at once
			$parts = array();
			foreach ($ids as $id) {
				$parts[] = "id = '".sqlescape($id)."'";
			}
			$settings = array();
			$q = sqlquery("SELECT * FROM bigtree_settings WHERE (".implode(" OR ",$parts).") ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				// If the setting is encrypted, we need to re-pull just the value.
				if ($f["encrypted"]) {
					$f = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".sqlescape($bigtree["config"]["settings_key"])."') AS `value` FROM bigtree_settings WHERE id = '".$f["id"]."'"));
				}
				$value = json_decode($f["value"],true);
				if (is_array($value)) {
					$settings[$f["id"]] = BigTree::untranslateArray($value);
				} else {
					$settings[$f["id"]] = static::replaceInternalPageLinks($value);
				}
			}
			return $settings;
		}
		
		/*
			Function: getTag
				Returns a tag for a given tag id.
			
			Parameters:
				id - The id of the tag to retrieve.
			
			Returns:
				A tag entry from bigtree_tags.
		*/
		
		static function getTag($id) {
			$id = sqlescape($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$id'"));
		}
		
		/*
			Function: getTagByRoute
				Returns a tag for a given tag route.
			
			Parameters:
				route - The route of the tag to retrieve.
			
			Returns:
				A tag entry from bigtree_tags.
		*/
		
		static function getTagByRoute($route) {
			$route = sqlescape($route);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE route = '$route'"));
		}
		
		/*
			Function: getTagsForPage
				Returns a list of tags the page was tagged with.
			
			Parameters:
				page - Either a page array (containing at least the page's ID) or a page ID.
			
			Returns:
				An array of tags.
		*/
		
		static function getTagsForPage($page) {
			if (!is_numeric($page)) {
				$page = $page["id"];
			}
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel ON bigtree_tags.id = bigtree_tags_rel.tag WHERE bigtree_tags_rel.`table` = 'bigtree_pages' AND bigtree_tags_rel.entry = '".sqlescape($page)."' ORDER BY bigtree_tags.tag");
			$tags = array();
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getTemplate
				Returns a template from the database with resources decoded.
			
			Parameters:
				id - The ID of the template.
			
			Returns:
				The template row from the database with resources decoded.
		*/
		
		static function getTemplate($id) {
			$id = sqlescape($id);
			$template = sqlfetch(sqlquery("SELECT * FROM bigtree_templates WHERE id = '$id'"));
			if (!$template) {
				return false;
			}
			$template["resources"] = json_decode($template["resources"],true);
			return $template;
		}
		
		/*
			Function: getToplevelNavigationId
				Returns the highest level ancestor for the current page.
			
			Parameters:
				trunk_as_toplevel - Treat a trunk as top level navigation instead of a new "site" (will return the trunk instead of the first nav item below the trunk if encountered) - defaults to false
			
			Returns:
				The ID of the highest ancestor of the current page.
			
			See Also:
				<getToplevelNavigationIdForPage>
			
		*/
		
		static function getTopLevelNavigationId($trunk_as_toplevel = false) {
			global $bigtree;
			return static::getTopLevelNavigationIdForPage($bigtree["page"],$trunk_as_toplevel);
		}
		
		/*
			Function: getToplevelNavigationIdForPage
				Returns the highest level ancestor for a given page.
			
			Parameters:
				page - A page array (containing at least the page's "path").
				trunk_as_toplevel - Treat a trunk as top level navigation instead of a new "site" (will return the trunk instead of the first nav item below the trunk if encountered) - defaults to false
			
			Returns:
				The ID of the highest ancestor of the given page.
			
			See Also:
				<getToplevelNavigationId>
			
		*/
		
		static function getTopLevelNavigationIdForPage($page,$trunk_as_toplevel = false) {
			$paths = array();
			$path = "";
			$parts = explode("/",$page["path"]);
			
			foreach ($parts as $part) {
				$path .= "/".$part;
				$path = ltrim($path,"/");
				$paths[] = "path = '".sqlescape($path)."'";
			}
			
			// Get either the trunk or the top level nav id.
			$f = sqlfetch(sqlquery("SELECT id,trunk,path FROM bigtree_pages WHERE (".implode(" OR ",$paths).") AND (trunk = 'on' OR parent = '".BIGTREE_SITE_TRUNK."') ORDER BY LENGTH(path) DESC LIMIT 1"));

			if ($f["trunk"] && !$trunk_as_toplevel) {
				// Get the next item in the path.
				$g = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE (".implode(" OR ",$paths).") AND LENGTH(path) < ".strlen($f["path"])." ORDER BY LENGTH(path) ASC LIMIT 1"));
				if ($g) {
					$f = $g;
				}
			}
			
			return $f["id"];
		}
		
		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		static function handle404($url) {
			$url = sqlescape(htmlspecialchars(strip_tags(rtrim($url,"/"))));
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_404s WHERE broken_url = '$url'"));
			if (!$url) {
				return true;
			}

			if ($f["redirect_url"]) {
				$f["redirect_url"] = static::getInternalPageLink($f["redirect_url"]);

				if ($f["redirect_url"] == "/") {
					$f["redirect_url"] = "";
				}
				
				if (substr($f["redirect_url"],0,7) == "http://" || substr($f["redirect_url"],0,8) == "https://") {
					$redirect = $f["redirect_url"];
				} else {
					$redirect = WWW_ROOT.str_replace(WWW_ROOT,"",$f["redirect_url"]);
				}
				
				sqlquery("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = '".$f["id"]."'");
				BigTree::redirect(htmlspecialchars_decode($redirect),"301");
				return false;
			} else {
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				if ($f) {
					sqlquery("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = '".$f["id"]."'");
				} else {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`,`requests`) VALUES ('$url','1')");
				}
				define("BIGTREE_DO_NOT_CACHE",true);
				return true;
			}
		}
		
		/*
		    Function: linkForPath
				Returns a correct link for a page's path for the current site in a multi-domain setup.
			
			Parameters:
				path - A page path
		
			Returns:
				A fully qualified URL
		*/
		
		static function linkForPath($path) {
			global $bigtree;
			
			// Remove the site root from the path for multi-site
			if (defined("BIGTREE_SITE_KEY") || (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]))) {
				foreach (static::$SiteRoots as $site_path => $site_data) {
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
			Function: makeSecure
				Forces the site into Secure mode.
				When Secure mode is enabled, BigTree will enforce the user being at HTTPS and will rewrite all insecure resources (like CSS, JavaScript, and images) to use HTTPS.
		*/
		
		static function makeSecure() {
			if (!$_SERVER["HTTPS"]) {
				BigTree::redirect("https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],"301");
			}
			static::$Secure = true;
		}
		
		/*
			Function: replaceHardRoots
				Replaces all hard roots in a URL with relative ones (i.e. {wwwroot}).

			Parameters:
				string - A string with hard roots.

			Returns:
				A string with relative roots.
		*/

		static function replaceHardRoots($string) {
			static::generateReplaceableRoots();
			
			return str_replace(static::$ReplaceableRootKeys, static::$ReplaceableRootVals, $string);
		}

		/*
			Function: replaceInternalPageLinks
				Replaces the internal page links in an HTML block with hard links.
			
			Parameters:
				html - An HTML block
			
			Returns:
				An HTML block with links hard-linked.
		*/
		
		static function replaceInternalPageLinks($html) {
			// Save time if there's no content
			if (trim($html) === "") {
				return "";
			}
			
			if (substr($html,0,6) == "ipl://" || substr($html,0,6) == "irl://") {
				$html = static::getInternalPageLink($html);
			} else {
				$html = static::replaceRelativeRoots($html);
				$html = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^',array("BigTreeCMS","replaceInternalPageLinksHook"),$html);
				$html = preg_replace_callback('^="(irl:\/\/[a-zA-Z0-9\_\:\/\.\?\=\-]*)"^',array("BigTreeCMS","replaceInternalPageLinksHook"),$html);
			}

			return $html;
		}
		static function replaceInternalPageLinksHook($matches) {
			return '="'.static::getInternalPageLink($matches[1]).'"';
		}
		
		/*
			Function: replaceRelativeRoots
				Replaces all relative roots in a URL (i.e. {wwwroot}) with hard links.

			Parameters:
				string - A string with relative roots.

			Returns:
				A string with hard links.
		*/

		static function replaceRelativeRoots($string) {
			static::generateReplaceableRoots();
			
			return str_replace(static::$ReplaceableRootVals, static::$ReplaceableRootKeys, $string);
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
			$title = str_replace("/","-",$title);
			$title = strtolower(preg_replace('/\s/', '-',preg_replace('/[^a-zA-Z0-9\s\-\_]+/', '',trim($title))));
			$title = str_replace("--","-",$title);
	
			return $title;
		}
	}
