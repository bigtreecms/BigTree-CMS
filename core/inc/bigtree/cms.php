<?php
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	class BigTreeCMSBase {
		
		// Public properties
		public $AutoSaveSettings = array();
		public $ExtensionRequiredFiles = array();
		public $ModuleClassList = array();
		public $RouteRegistry = array("public" => array(),"admin" => array(),"template" => array());

		// Public static properties
		public static $BreadcrumbTrunk;
		public static $DB;
		public static $IRLCache = array();
		public static $IPLCache = array();
		public static $MySQLTime = false;
		public static $ReplaceableRootKeys = array();
		public static $ReplaceableRootVals = array();
		public static $Secure;

		/*
			Constructor:
				Builds a flat file module class list so that module classes can be autoloaded instead of always in memory.
		*/
		
		function __construct() {
			global $bigtree;

			// If the cache exists, just use it.
			if (!$bigtree["config"]["debug"] && file_exists(SERVER_ROOT."cache/bigtree-module-cache.json")) {
				$data = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-module-cache.json"),true);
			} else {
				$data = array(
					"routes" => array("admin" => array(),"public" => array(),"template" => array()),
					"classes" => array(),
					"extension_required_files" => array()
				);

				// Preload the BigTreeModule class since others are based off it
				include_once BigTree::path("inc/bigtree/modules.php");

				// Get all modules from the db
				$modules = static::$DB->fetchAll("SELECT route,class FROM bigtree_modules");
				foreach ($modules as $module) {
					$class = $module["class"];
					$route = $module["route"];

					if ($class) {
						// Get the class file path
						if (strpos($route,"*") !== false) {
							list($extension,$file_route) = explode("*",$route);
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
								$this->RouteRegistry[$type][] = $registration;
							}
						}
					}
				}

				// Get all extension required files and add them to a required list
				$extensions = static::$DB->fetchAllSingle("SELECT id FROM bigtree_extensions");
				foreach ($extensions as $id) {
					$required_contents = BigTree::directoryContents(SERVER_ROOT."extensions/$id/required/");
					foreach (array_filter((array)$required_contents) as $file) {
						$data["extension_required_files"][] = $file;
					}
				}
				
				if (!$bigtree["config"]["debug"]) {
					// Cache it so we don't hit the database.
					BigTree::putFile(SERVER_ROOT."cache/bigtree-module-cache.json",BigTree::json($data));
				}
			}

			$this->ExtensionRequiredFiles = $data["extension_required_files"];
			$this->ModuleClassList = $data["classes"];
			$this->RouteRegistry = $data["routes"];
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
				name - Optional name for the setting.

			Returns:
				An object reference.
		*/

		function &autoSaveSetting($id,$return_object = true,$name = "") {
			$id = static::extensionSettingCheck($id);

			// Only want one usage to exist
			if (!isset($this->AutoSaveSettings[$id])) {
				$data = $this->getSetting($id);

				// Create a setting if it doesn't exist yet
				if ($data === false) {

					// If an extension is creating an auto save setting, make it a reference back to the extension
					if (defined("EXTENSION_ROOT") && strpos($id,"bigtree-internal-") !== 0) {
						$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
						
						// Don't append extension again if it's already being called via the namespace
						if (strpos($id,"$extension*") === false) {
							$id = "$extension*$id";
						}
						
						static::$DB->insert("bigtree_settings",array(
							"id" => $id,
							"name" => $name,
							"encrypted" => "on",
							"system" => "on",
							"extension" => $extension
						));
					} else {
						static::$DB->insert("bigtree_settings",array(
							"id" => $id,
							"name" => $name,
							"encrypted" => "on",
							"system" => "on"
						));
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

		function cacheDelete($identifier,$key = false) {
			if ($key === false) {
				static::$DB->query("DELETE FROM bigtree_caches WHERE `identifier` = ?",$identifier);
			} else {
				static::$DB->query("DELETE FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?",$identifier,$key);
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
				Data from the table (json decoded, objects convert to keyed arrays) if it exists.
		*/

		static function cacheGet($identifier,$key,$max_age = false,$decode = true) {
			if ($max_age) {
				// We need to get MySQL's idea of what time it is so that if PHP's differs we don't screw up caches.
				if (!static::$MySQLTime) {
					static::$MySQLTime = static::$DB->fetchSingle("SELECT NOW()");
				}
				$max_age = date("Y-m-d H:i:s",strtotime(static::$MySQLTime) - $max_age);

				$entry = static::$DB->fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ? AND timestamp >= ?",$identifier,$key,$max_age);
			} else {
				$entry = static::$DB->fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?",$identifier,$key);
			}

			return $decode ? json_decode($entry,true) : $entry;
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
			$exists = static::$DB->exists("bigtree_caches",array("identifier" => $identifier,"key" => $key));
			if (!$replace && $exists) {
				return false;
			}

			$value = BigTree::json($value);
			
			if ($exists) {
				return static::$DB->update("bigtree_caches",array("identifier" => $identifier,"key" => $key),array("value" => $value));
			} else {
				return static::$DB->insert("bigtree_caches",array("identifier" => $identifier,"key" => $key,"value" => $value));
			}
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
				$result = static::$DB->fetch("SELECT * FROM bigtree_route_history WHERE old_route = ?",implode("/",array_slice($path,0,$x)));
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
			
			$pages = static::$DB->fetchAll("SELECT id,template,external,path FROM bigtree_pages WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");
			foreach ($pages as $page) {
				if ($page["template"] || strpos($page["external"],DOMAIN)) {	
					if (!$page["template"]) {
						$link = static::getInternalPageLink($page["external"]);
					} else {
						$link = WWW_ROOT.$page["path"].(($page["id"] > 0) ? "/" : ""); // Fix sitemap adding trailing slashes to home
					}
					
					echo "<url><loc>".$link."</loc></url>\n";
					
					// Added routed template support
					$module_class = static::$DB->fetchSingle("SELECT bigtree_modules.class
															  FROM bigtree_templates JOIN bigtree_modules 
															  ON bigtree_modules.id = bigtree_templates.module
															  WHERE bigtree_templates.id = ?",$page["template"]);
					if ($module_class) {
						$module = new $module_class;
						if (method_exists($module,"getSitemap")) {
							$subnav = $module->getSitemap($page);
							foreach ($subnav as $entry) {
								echo "<url><loc>".$entry["link"]."</loc></url>\n";
							}
						}
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
			// See if we're in an extension
			if (defined("EXTENSION_ROOT")) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
				
				// If we're already asking for it by it's namespaced name, don't append again.
				if (substr($id,0,strlen($extension)) == $extension) {
					return $id;
				}
				
				// See if namespaced version exists
				if (static::$DB->exists("bigtree_settings",array("id" => "$extension*$id"))) {
					return "$extension*$id";
				}
			}
			
			return $id;
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

			$bc = array();
			
			// Break up the pieces so we can get each piece of the path individually and pull all the pages above this one.
			$pieces = explode("/",$page["path"]);
			$paths = array();
			$path = "";
			foreach ($pieces as $piece) {
				$path = $path.$piece."/";
				$paths[] = "path = '".static::$DB->escape(trim($path,"/"))."'";
			}
			
			// Get all the ancestors, ordered by the page length so we get the latest first and can count backwards to the trunk.
			$ancestors = static::$DB->fetchAll("SELECT id,nav_title,path,trunk FROM bigtree_pages WHERE (".implode(" OR ",$paths).") ORDER BY LENGTH(path) DESC");
			$trunk_hit = false;
			foreach ($ancestors as $ancestor) {
				// In case we want to know what the trunk is.
				if ($ancestor["trunk"]) {
					$trunk_hit = true;
					static::$BreadcrumbTrunk = $ancestor;
				}
				
				if (!$trunk_hit || $ignore_trunk) {
					if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
						$link = WWW_ROOT.$ancestor["path"];
					} else {						
						$link = WWW_ROOT.$ancestor["path"]."/";
					}
					$bc[] = array("title" => stripslashes($ancestor["nav_title"]),"link" => $link,"id" => $ancestor["id"]);
				}
			}
			$bc = array_reverse($bc);
			
			// Check for module breadcrumbs
			$module_class = static::$DB->fetchSingle("SELECT bigtree_modules.class
												FROM bigtree_modules JOIN bigtree_templates
												ON bigtree_modules.id = bigtree_templates.module
												WHERE bigtree_templates.id = ?",$page["template"]);

			if ($module_class && class_exists($module_class)) {
				$module = new $module_class;
				if (method_exists($module, "getBreadcrumb")) {
					$bc = array_merge($bc,$module->getBreadcrumb($page));
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
				$item = static::$DB->fetch("SELECT * FROM bigtree_feeds WHERE id = ?",$item);
			}

			if (!$item) {
				return false;
			}

			$item["fields"] = json_decode($item["fields"],true);
			$item["options"] = json_decode($item["options"],true);
			if (is_array($item["options"])) {
				foreach ($item["options"] as &$option) {
					$option = static::replaceRelativeRoots($option);
				}
			}
			
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
			return static::getFeed(static::$DB->fetch("SELECT * FROM bigtree_feeds WHERE route = ?",$route));
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
					$resource = static::$DB->fetch("SELECT * FROM bigtree_resources WHERE id = ?",$navid);
					$file = $resource ? static::replaceRelativeRoots($resource["file"]) : false;
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
				if (strpos($last,"#") === false && strpos($last,"?") === false) {
					$commands .= "/";
				}
			} else {
				$commands = "";
			}

			// See if it's in the cache.
			if (isset(static::$IPLCache[$navid])) {
				if ($bigtree["config"]["trailing_slash_behavior"] != "none" || $commands != "") {
					return static::$IPLCache[$navid]."/".$commands;
				} else {
					return static::$IPLCache[$navid];
				}
			} else {
				// Get the page's path
				$path = static::$DB->fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?",$navid);

				// Set the cache
				static::$IPLCache[$navid] = WWW_ROOT.$path;

				if ($bigtree["config"]["trailing_slash_behavior"] != "none" || $commands != "") {
					return WWW_ROOT.$path."/".$commands;
				} else {
					return WWW_ROOT.$path;
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
			if ($id == 0) {
				return WWW_ROOT;
			}

			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
					return WWW_ROOT.$bigtree["page"]["path"];					
				} else {
					return WWW_ROOT.$bigtree["page"]["path"]."/";
				}
			}

			// Otherwise we'll grab the page path from the db.
			$path = static::$DB->fetchSingle("SELECT path FROM bigtree_pages WHERE archived != 'on' AND id = ?",$id);
			if ($path) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
					return WWW_ROOT.$path;
				} else {
					return WWW_ROOT.$path."/";
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
			
			Returns:
				A multi-level navigation array containing "id", "parent", "title", "route", "link", "new_window", and "children"
		*/
			
		static function getNavByParent($parent = 0,$levels = 1,$follow_module = true,$only_hidden = false) {
			global $bigtree;

			static $module_nav_count = 0;
			$nav = array();
			$find_children = array();
			
			// If the parent is an array, this is actually a recursed call.
			// We're finding all the children of all the parents at once -- then we'll assign them back to the proper parent instead of doing separate calls for each.
			if (is_array($parent)) {
				$where_parent = array();
				foreach ($parent as $p) {
					$where_parent[] = "parent = '".static::$DB->escape($p)."'";
				}
				$where_parent = "(".implode(" OR ",$where_parent).")";
			// If it's an integer, let's just pull the children for the provided parent.
			} else {
				$parent = static::$DB->escape($parent);
				$where_parent = "parent = '$parent'";
			}
			
			$in_nav = $only_hidden ? "" : "on";
			$sort = $only_hidden ? "nav_title ASC" : "position DESC, id ASC";
			
			$children = static::$DB->fetchAll("SELECT id,nav_title,parent,external,new_window,template,route,path 
											   FROM bigtree_pages
											   WHERE $where_parent AND
											   		 in_nav = '$in_nav' AND
											   		 archived != 'on' AND
											   		 (publish_at <= NOW() OR publish_at IS NULL) AND 
											   		 (expire_at >= NOW() OR expire_at IS NULL) 
											   ORDER BY $sort");
			
			// Wrangle up some kids
			foreach ($children as $child) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "none") {
					$link = WWW_ROOT.$child["path"];
				} else {
					$link = WWW_ROOT.$child["path"]."/";
				}

				$new_window = false;
				
				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.  Then we'll see if we should open this thing in a new window.
				if ($child["external"] && $child["template"] == "") {
					$link = static::getInternalPageLink($child["external"]);
					if ($child["new_window"]) {
						$new_window = true;
					}
				}
				
				// Add it to the nav array
				$nav[$child["id"]] = array(
					"id" => $child["id"],
					"parent" => $child["parent"],
					"title" => $child["nav_title"],
					"route" => $child["route"],
					"link" => $link,
					"new_window" => $new_window,
					"children" => array()
				);
				
				// If we're going any deeper, mark down that we're looking for kids of this kid.
				if ($levels > 1) {
					$find_children[] = $child["id"];
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
						$where_parent[] = "bigtree_pages.id = '".static::$DB->escape($p)."'";
					}

					$module_pages = static::$DB->fetchAll("SELECT bigtree_modules.class,
														   		  bigtree_templates.routed,
																  bigtree_templates.module,
																  bigtree_pages.id,
																  bigtree_pages.path,
																  bigtree_pages.template
														   FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages 
														   ON bigtree_templates.id = bigtree_pages.template 
														   WHERE bigtree_modules.id = bigtree_templates.module AND 
														   		 (".implode(" OR ",$where_parent).")");
					foreach ($module_pages as $module_page) {
						// If the class exists, instantiate it and call it
						if ($module_page["class"] && class_exists($module_page["class"])) {
							$module = new $module_page["class"];
							if (method_exists($module,"getNav")) {
								$modNav = $module->getNav($module_page);
								// Give the parent back to each of the items it returned so they can be reassigned to the proper parent.
								$module_nav = array();
								foreach ($modNav as $item) {
									$item["parent"] = $module_page["id"];
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
					$module_page = static::$DB->fetch("SELECT bigtree_modules.class,
															  bigtree_templates.routed,
															  bigtree_templates.module,
															  bigtree_pages.id,
															  bigtree_pages.path,
															  bigtree_pages.template 
													   FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages 
													   ON bigtree_templates.id = bigtree_pages.template 
													   WHERE bigtree_modules.id = bigtree_templates.module AND 
													   		 bigtree_pages.id = ?",$parent);
					// If the class exists, instantiate it and call it.
					if ($module_page["class"] && class_exists($module_page["class"])) {
						$module = new $module_page["class"];
						if (method_exists($module,"getNav")) {
							if ($module->NavPosition == "top") {
								$nav = array_merge($module->getNav($module_page),$nav);
							} else {
								$nav = array_merge($nav,$module->getNav($module_page));
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
			$publish_at = $previewing ? "" : "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			
			// See if we have a straight up perfect match to the path.
			$page = static::$DB->fetch("SELECT bigtree_pages.id,bigtree_templates.routed
										FROM bigtree_pages LEFT JOIN bigtree_templates
										ON bigtree_pages.template = bigtree_templates.id
										WHERE path = ? AND archived = '' $publish_at",implode("/",$path));
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
				$page_id = static::$DB->fetchSingle("SELECT bigtree_pages.id
													 FROM bigtree_pages JOIN bigtree_templates 
													 ON bigtree_pages.template = bigtree_templates.id 
													 WHERE bigtree_pages.path = ? AND 
														   bigtree_pages.archived = '' AND
														   bigtree_templates.routed = 'on' $publish_at",$path_string);
				if ($page_id) {
					return array($page_id,array_reverse($commands),"on");
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
			$page = static::$DB->fetch("SELECT * FROM bigtree_pages WHERE id = ?",$id);
			if (!$page) {
				return false;
			}

			if ($page["external"] && $page["template"] == "") {
				$page["external"] = static::getInternalPageLink($page["external"]);
			}

			if ($decode) {
				$page["resources"] = static::decodeResources($page["resources"]);
				
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
				$changes = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?",$page["id"]);

			// If it's prefixed with a "p" then it's a pending entry.
			} else {
				// Set the page to empty, we're going to loop through the change later and apply the fields.
				$page = array();

				// Get the changes.
				$changes = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE `id` = ?",substr($id,1));
				if (!$changes) {
					return false;
				}
				
				$changes["id"] = $id;
			}

			// If we have changes, apply them.
			if ($changes) {
				$page["changes_applied"] = true;
				$page["updated_at"] = $changes["date"];
				$resource_changes = json_decode($changes["changes"],true);
				foreach ($resource_changes as $key => $val) {
					if ($key == "external") {
						$val = static::getInternalPageLink($val);
					}
					$page[$key] = $val;
				}
				if ($return_tags) {
					// Decode the tag changes, apply them back.
					$tags = array();
					$tags_changes = json_decode($changes["tags_changes"],true);
					if (is_array($tags_changes)) {
						foreach ($tags_changes as $tag) {
							$tags[] = static::$DB->fetch("SELECT * FROM bigtree_tags WHERE id = ?",$tag);
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
				return WWW_ROOT."_preview-pending/".htmlspecialchars($id)."/";
			} elseif ($id == 0) {
				return WWW_ROOT."_preview/";
			} else {
				$path = static::$DB->fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?",$id);
				return WWW_ROOT."_preview/$path/";
			}
		}
		
		/*
			Function: getRelatedPagesByTags
				Returns pages related to the given set of tags.
			
			Parameters:
				tags - An array of tags to search for.
				only_id - Whether to return only the page IDs (defaults to false)
			
			Returns:
				An array of related pages sorted by relevance (how many tags get matched).
		*/
		
		static function getRelatedPagesByTags($tags = array(),$only_id = false) {
			$results = array();
			$relevance = array();

			// Loop through each tag finding related pages
			foreach ($tags as $tag) {
				// In case a whole tag row was passed
				if (is_array($tag)) {
					$tag = $tag["tag"];
				}

				$tag_id = static::$DB->fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?",$tag);
				if ($tag_id) {
					$related_pages = static::$DB->fetchAllSingle("SELECT entry FROM bigtree_tags_rel WHERE tag = ? AND `table` = 'bigtree_pages'",$tag_id);

					foreach ($related_pages as $page_id) {
						// If we already have this result, add relevance
						if (in_array($page_id,$results)) {
							$relevance[$page_id]++;
						} else {
							$results[] = $page_id;
							$relevance[$page_id] = 1;
						}
					}
				}
			}

			// Sort by most relevant
			array_multisort($relevance,SORT_DESC,$results);

			if ($only_id) {
				return $results;
			}

			// Get the actual page data for each result
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

			$setting = static::$DB->fetch("SELECT * FROM bigtree_settings WHERE id = ?",$id);
			// Setting doesn't exist
			if (!$setting) {
				return false;
			}

			// If the setting is encrypted, we need to re-pull just the value.
			if ($setting["encrypted"]) {
				$setting["value"] = static::$DB->fetchSingle("SELECT AES_DECRYPT(`value`,?) FROM bigtree_settings WHERE id = ?",$bigtree["config"]["settings_key"],$id);
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
				$parts[] = "id = '".static::$DB->escape($id)."'";
			}

			$settings = array();
			$settings_list = static::$DB->fetchAll("SELECT * FROM bigtree_settings WHERE (".implode(" OR ",$parts).") ORDER BY id ASC");
			foreach ($settings_list as $setting) {
				// If the setting is encrypted, we need to re-pull just the value.
				if ($setting["encrypted"]) {
					$setting["value"] = static::$DB->fetchSingle("SELECT AES_DECRYPT(`value`,?) FROM bigtree_settings WHERE id = ?",$bigtree["config"]["settings_key"],$setting["id"]);
				}

				$value = json_decode($setting["value"],true);
				if (is_array($value)) {
					$settings[$setting["id"]] = BigTree::untranslateArray($value);
				} else {
					$settings[$setting["id"]] = static::replaceInternalPageLinks($value);
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
			return static::$DB->fetch("SELECT * FROM bigtree_tags WHERE id = ?",$id);
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
			return static::$DB->fetch("SELECT * FROM bigtree_tags WHERE route = ?", $route);
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

			return static::$DB->fetchAll("SELECT bigtree_tags.*
										  FROM bigtree_tags JOIN bigtree_tags_rel 
										  ON bigtree_tags.id = bigtree_tags_rel.tag 
										  WHERE bigtree_tags_rel.`table` = 'bigtree_pages' AND 
												bigtree_tags_rel.entry = ?
										  ORDER BY bigtree_tags.tag",$page);
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
			$template = static::$DB->fetch("SELECT * FROM bigtree_templates WHERE id = ?",$id);
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
				$paths[] = "path = '".static::$DB->escape($path)."'";
			}

			// Get either the trunk or the top level nav id.
			$page = static::$DB->fetch("SELECT id, trunk, path
										FROM bigtree_pages
										WHERE (".implode(" OR ",$paths).") AND
											  (trunk = 'on' OR parent = '0')
										ORDER BY LENGTH(path) DESC
										LIMIT 1");

			// If we don't want the trunk, look higher
			if ($page["trunk"] && $page["parent"] && !$trunk_as_toplevel) {
				// Get the next item in the path.
				$id = static::$DB->fetchSingle("SELECT id 
												FROM bigtree_pages 
												WHERE (".implode(" OR ",$paths).") AND 
													  LENGTH(path) < ".strlen($page["path"])." 
												ORDER BY LENGTH(path) ASC
												LIMIT 1");
				if ($id) {
					return $id;
				}
			}

			return $page["id"];
		}
		
		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		static function handle404($url) {
			$url = htmlspecialchars(strip_tags(rtrim($url,"/")));
			if (!$url) {
				return false;
			}

			$entry = static::$DB->fetch("SELECT * FROM bigtree_404s WHERE broken_url = ?",$url);
			
			// We already have a redirect
			if ($entry["redirect_url"]) {
				$entry["redirect_url"] = static::getInternalPageLink($entry["redirect_url"]);

				// If we're redirecting to the homepage, don't add additional trailing slashes
				if ($entry["redirect_url"] == "/") {
					$entry["redirect_url"] = "";
				}
				
				// Full URL, use the whole thing
				if (substr($entry["redirect_url"],0,7) == "http://" || substr($entry["redirect_url"],0,8) == "https://") {
					$redirect = $entry["redirect_url"];
				
				// Partial URL, append WWW_ROOT
				} else {
					$redirect = WWW_ROOT.str_replace(WWW_ROOT,"",$entry["redirect_url"]);
				}
				
				// Update request count
				static::$DB->query("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = ?",$entry["id"]);

				// Redirect with a 301
				BigTree::redirect(htmlspecialchars_decode($redirect),"301");

			// No redirect, log the 404 and throw the 404 headers
			} else {
				// Throw 404 header
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");

				if ($entry) {
					static::$DB->query("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = ?",$entry["id"]);
				} else {
					static::$DB->insert("bigtree_404s",array(
						"broken_url" => $url,
						"requests" => 1
					));
				}

				// Tell BigTree to not cache this page
				define("BIGTREE_DO_NOT_CACHE",true);
			}

			return true;
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
			// Figure out what roots we can replace
			if (!count(static::$ReplaceableRootKeys)) {
				if (substr(ADMIN_ROOT,0,7) == "http://" || substr(ADMIN_ROOT,0,8) == "https://") {
					static::$ReplaceableRootKeys[] = ADMIN_ROOT;
					static::$ReplaceableRootVals[] = "{adminroot}";
				}
				if (substr(STATIC_ROOT,0,7) == "http://" || substr(STATIC_ROOT,0,8) == "https://") {
					static::$ReplaceableRootKeys[] = STATIC_ROOT;
					static::$ReplaceableRootVals[] = "{staticroot}";
				}
				if (substr(WWW_ROOT,0,7) == "http://" || substr(WWW_ROOT,0,8) == "https://") {
					static::$ReplaceableRootKeys[] = WWW_ROOT;
					static::$ReplaceableRootVals[] = "{wwwroot}";
				}
			}
			return str_replace(static::$ReplaceableRootKeys,static::$ReplaceableRootVals,$string);
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
			return str_replace(array("{adminroot}","{wwwroot}","{staticroot}"),array(ADMIN_ROOT,WWW_ROOT,STATIC_ROOT),$string);
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
