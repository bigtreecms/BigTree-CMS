<?
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	class BigTreeCMS {
	
		var $iplCache = array();

		/*
			Constructor:
				Builds a flat file module class list so that module classes can be autoloaded instead of always in memory.
		*/
		
		function __construct() {
			// If the cache exists, just use it.
			if (file_exists(SERVER_ROOT."cache/module-class-list.btc")) {
				$items = json_decode(file_get_contents(SERVER_ROOT."cache/module-class-list.btc"),true);
			} else {
				// Get the Module Class List
				$q = sqlquery("SELECT * FROM bigtree_modules");
				$items = array();
				while ($f = sqlfetch($q)) {
					$items[$f["class"]] = $f["route"];
				}
				
				// Cache it so we don't hit the database.
				file_put_contents(SERVER_ROOT."cache/module-class-list.btc",json_encode($items));
			}
			
			$this->ModuleClassList = $items;
		}

		/*
			Function: cacheGet
				Retrieves data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data.
				max_age - The maximum age (in seconds) for the data, defaults to any age.

			Returns:
				Data from the table (json decoded, objects convert to keyed arrays) if it exists or false.
		*/

		function cacheGet($identifier,$key,$max_age = false) {
			$identifier = sqlescape($identifier);
			$key = sqlescape($key);
			if ($max_age) {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key' AND timestamp >= '".date("Y-m-d H:i:s",time() - $max_age)."'"));
			} else {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key'"));
			}
			if (!$f) {
				return false;
			}
			return json_decode($f["value"],true);
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

		function cachePut($identifier,$key,$value,$replace = true) {
			$identifier = sqlescape($identifier);
			$key = sqlescape($key);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_caches WHERE `identifier` = '$identifier' AND `key` = '$key'"));
			if ($f && !$replace) {
				return false;
			}

			// Prefer to keep this an object, but we need PHP 5.3
			if (strnatcmp(phpversion(),'5.3') >= 0) {
				$value = sqlescape(json_encode($value,JSON_FORCE_OBJECT));			
			} else {
				$value = sqlescape(json_encode($value));
			}
			
			if ($f) {
				sqlquery("UPDATE bigtree_caches SET `value` = '$value' WHERE `identifier` = '$identifier' AND `key` = '$key'");
			} else {
				sqlquery("INSERT INTO bigtree_caches (`identifier`,`key`,`value`) VALUES ('$identifier','$key','$value')");
			}
			return true;
		}
		
		/*
			Function: catch404
				Manually catch and display the 404 page from a routed template; logs missing page with handle404
		*/
		
		function catch404() {
			global $cms,$bigtree;
			
			if ($this->handle404(str_ireplace(WWW_ROOT,"",BigTree::currentURL()))) {
				$bigtree["layout"] = "default";
				ob_start();
				include "../templates/basic/_404.php";
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
		
		function checkOldRoutes($path) {
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
			Function: decodeCallouts
				Turns the JSON callout data into a PHP array of callouts with links being translated into front-end readable links.
				This function is called by BigTree's router and is generally not a function needed to end users.
			
			Parameters:
				data - JSON encoded callout data.
			
			Returns:
				An array of callouts.
		*/	
			
		function decodeCallouts($data) {
			$parsed = array();
			if (!is_array($data)) {
				$data = json_decode($data,true);
			}
			// Just in case it was empty, we do an is_array to avoid warnings
			if (is_array($data)) {
				foreach ($data as $key => $d) {
					$p = array();
					foreach ($d as $kk => $dd) {
						if (is_array($dd)) {
							// If this value is an array, untranslate it so that {wwwroot} and ipls get fixed.
							$p[$kk] = BigTree::untranslateArray($dd);
						} elseif (is_array(json_decode($dd,true))) {
							// If this value is an array, untranslate it so that {wwwroot} and ipls get fixed.
							$p[$kk] = BigTree::untranslateArray(json_decode($dd,true));
						} else {
							// Otherwise it's a string, just replace the {wwwroot} and ipls.
							$p[$kk] = $this->replaceInternalPageLinks($dd);
						}
					}
					$parsed[$key] = $p;
				}
			}
			return $parsed;
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

		function decodeResources($data) {
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
						$val = $this->replaceInternalPageLinks($val);				
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
		
		function drawXMLSitemap() {
			header("Content-type: text/xml");
			echo '<?xml version="1.0" encoding="UTF-8" ?>';
			echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
			$q = sqlquery("SELECT id,template,external,path FROM bigtree_pages WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");

			while ($f = sqlfetch($q)) {
				if ($f["template"] || strpos($f["external"],$GLOBALS["domain"])) {	
					if (!$f["template"]) {
						$link = $this->getInternalPageLink($f["external"]);
					} else {
						$link = WWW_ROOT.$f["path"].(($f["id"] > 0) ? "/" : ""); // Fix sitemap adding trailing slashes to home
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
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for pages above the current page.
			
			Parameters:
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumbByPage>
		*/
		
		function getBreadcrumb($ignore_trunk = false) {
			global $bigtree;
			return $this->getBreadcrumbByPage($bigtree["page"],$ignore_trunk);
		}
		
		/*
			Function: getBreadcrumbByPage
				Returns an array of titles, links, and ids for the pages above the given page.
			
			Parameters:
				page - A page array (containing at least the "path" from the database) *(optional)*
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
				If a trunk is hit, $this->BreadCrumb trunk is set to the trunk.
			
			See Also:
				<getBreadcrumb>
		*/
		
		function getBreadcrumbByPage($page,$ignore_trunk = false) {
			$bc = array();
			
			// Break up the pieces so we can get each piece of the path individually and pull all the pages above this one.
			$pieces = explode("/",$page["path"]);
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
				if ($f["trunk"]) {
					$trunk_hit = true;
					$this->BreadcrumbTrunk = $f;
				}
				
				if (!$trunk_hit || $ignore_trunk) {
					$bc[] = array("title" => stripslashes($f["nav_title"]),"link" => WWW_ROOT.$f["path"]."/","id" => $f["id"]);
				}
			}
			
			$bc = array_reverse($bc);
			
			// Check for module breadcrumbs
			$mod = sqlfetch(sqlquery("SELECT bigtree_modules.class FROM bigtree_modules JOIN bigtree_templates ON bigtree_modules.id = bigtree_templates.module WHERE bigtree_templates.id = '".$page["template"]."'"));
			if ($mod["class"]) {
				if (class_exists($mod["class"])) {
					@eval('$module = new '.$mod["class"].';');
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
		
		function getFeed($item) {
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
					$option = $this->replaceRelativeRoots($option);
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
		
		function getFeedByRoute($route) {
			$route = sqlescape($route);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_feeds WHERE route = '$route'"));
			return $this->getFeed($item);
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
		
		function getHiddenNavByParent($parent = 0) {
			return $this->getNavByParent($parent,1,false,true);
		}
		
		/*
			Function: getInternalPageLink
				Returns a hard link to the page's publicly accessible URL from its encoded soft link URL.
			
			Parameters:
				ipl - Internal Page Link (ipl://, {wwwroot}, or regular URL encoding)
			
			Returns:
				Public facing URL.
		*/
		
		function getInternalPageLink($ipl) {
			if (substr($ipl,0,6) != "ipl://") {
				return $this->replaceRelativeRoots($ipl);
			}
			$ipl = explode("//",$ipl);
			$navid = $ipl[1];
			
			// New IPLs are encoded in JSON
			$c = json_decode(base64_decode($ipl[2]));
			// Help with transitions.
			if (!is_array($c)) {
				$c = unserialize(base64_decode($ipl[2]));
			}
			// If it can't be rectified, we still don't want a warning.
			if (is_array($c)) {
				$commands = implode("/",$c);
			} else {
				$commands = "";
			}
			
			if ($commands && strpos($commands,"?") === false) {
				$commands .= "/";
			}
			
			// See if it's in the cache.
			if (isset($this->iplCache[$navid])) {
				return $this->iplCache[$navid].$commands;
			} else {
				// Get the page's path
				$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".sqlescape($navid)."'"));
				// Set the cache
				$this->iplCache[$navid] = WWW_ROOT.$f["path"]."/";
				return WWW_ROOT.$f["path"]."/".$commands;
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
		
		function getLink($id) {
			global $bigtree;
			// Homepage, just return the web root.
			if ($id == 0) {
				return WWW_ROOT;
			}
			// If someone is requesting the link of the page they're already on we don't need to request it from the database.
			if ($bigtree["page"]["id"] == $id) {
				return WWW_ROOT.$bigtree["page"]["path"]."/";
			}
			// Otherwise we'll grab the page path from the db.
			$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".sqlescape($id)."'"));
			return WWW_ROOT.$f["path"]."/";
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
			
		function getNavByParent($parent = 0,$levels = 1,$follow_module = true,$only_hidden = false) {
			static $module_nav_count = 0;
			$nav = array();
			$find_children = array();
			
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
				$link = WWW_ROOT.$f["path"]."/";
				$new_window = false;
				
				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.  Then we'll see if we should open this thing in a new window.
				if ($f["external"] && $f["template"] == "") {
					$link = $this->getInternalPageLink($f["external"]);
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
				$subnav = $this->getNavByParent($find_children,$levels - 1,$follow_module);
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
							@eval('$module = new '.$f["class"].';');
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
						@eval('$module = new '.$f["class"].';');
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
			
			Paramaters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.
			
			Returns:
				An array containing the page ID and any additional commands.
		*/
		
		function getNavId($path,$previewing = false) {
			$commands = array();
			
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
				child - The ID of the page.
				decode - Whether to decode resources and callouts or not (setting to false saves processing time)
			
			Returns:
				A page array from the database.
		*/
		
		function getPage($child,$decode = true) {
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$child'"));
			if (!$f) {
				return false;
			}
			if ($f["external"] && $f["template"] == "") {
				$f["external"] = $this->getInternalPageLink($f["external"]);
			}
			if ($decode) {
				$f["resources"] = $this->decodeResources($f["resources"]);
				$f["callouts"] = $this->decodeCallouts($f["callouts"]);
			}
			return $f;
		}
		
		/*
			Function: getPendingPage
				Returns a page along with pending changes applied.
			
			Parameters:
				child - The ID of the page.
				decode - Whether to decode resources and callouts or not (setting to false saves processing time, defaults true).
				return_tags - Whether to return tags for the page (defaults false).
			
			Returns:
				A page array from the database.
		*/
		
		function getPendingPage($id,$decode = true,$return_tags = false) {
			// Numeric id means the page is live.
			if (is_numeric($id)) {
				$page = $this->getPage($id);
				if (!$page) {
					return false;
				}
				// If we're looking for tags, apply them to the page.
				if ($return_tags) {
					$page["tags"] = $this->getTagsForPage($id);
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
						$val = $this->getInternalPageLink($val);
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
					$page["resources"] = $this->decodeResources($page["resources"]);	
				}
				if (isset($page["callouts"]) && is_array($page["callouts"])) {
					$page["callouts"] = $this->decodeCallouts($page["callouts"]);
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
		
		function getPreviewLink($id) {
			if (substr($id,0,1) == "p") {
				return WWW_ROOT."_preview-pending/$id/";
			} elseif ($id == 0) {
				return WWW_ROOT."_preview/";
			} else {
				$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".sqlescape($id)."'"));
				return WWW_ROOT."_preview/".$f["path"]."/";
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
		
		function getRelatedPagesByTags($tags = array()) {
			$results = array();
			$relevance = array();
			foreach ($tags as $tag) {
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
				$items[] = $this->getPage($result);
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
		
		function getSetting($id) {
			global $bigtree;
			$id = sqlescape($id);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_settings WHERE id = '$id'"));
			// If the setting is encrypted, we need to re-pull just the value.
			if ($f["encrypted"]) {
				$f = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".sqlescape($bigtree["config"]["settings_key"])."') AS `value`, system FROM bigtree_settings WHERE id = '$id'"));
			}
			$value = json_decode($f["value"],true);

			// Don't try to do translations and such if it's a system value.
			if ($f["system"]) {
				return $value;
			}
			
			if (is_array($value)) {
				return BigTree::untranslateArray($value);
			} else {
				return $this->replaceInternalPageLinks($value);
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
		
		function getSettings($ids) {
			global $bigtree;
			if (!is_array($ids)) {
				$ids = array($ids);
			}
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
					$settings[$f["id"]] = $this->replaceInternalPageLinks($value);
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
		
		function getTag($id) {
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
		
		function getTagByRoute($route) {
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
		
		function getTagsForPage($page) {
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
		
		function getTemplate($id) {
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
		
		function getTopLevelNavigationId($trunk_as_toplevel = false) {
			global $bigtree;
			return $this->getTopLevelNavigationIdForPage($bigtree["page"],$trunk_as_toplevel);
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
		
		function getTopLevelNavigationIdForPage($page,$trunk_as_toplevel = false) {
			$paths = array();
			$path = "";
			$parts = explode("/",$page["path"]);
			foreach ($parts as $part) {
				$path .= "/".$part;
				$path = ltrim($path,"/");
				$paths[] = "path = '".sqlescape($path)."'";
			}
			// Get either the trunk or the top level nav id.
			$f = sqlfetch(sqlquery("SELECT id,trunk,path FROM bigtree_pages WHERE (".implode(" OR ",$paths).") AND (trunk = 'on' OR parent = '0') ORDER BY LENGTH(path) DESC LIMIT 1"));
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
		
		function handle404($url) {
			$url = sqlescape(htmlspecialchars(strip_tags(rtrim($url,"/"))));
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_404s WHERE broken_url = '$url'"));
			if (!$url) {
				return true;
			}

			if ($f["redirect_url"]) {
				if ($f["redirect_url"] == "/") {
					$f["redirect_url"] = "";
				}
				
				if (substr($f["redirect_url"],0,7) == "http://" || substr($f["redirect_url"],0,8) == "https://") {
					$redirect = $f["redirect_url"];
				} else {
					$redirect = WWW_ROOT.str_replace(WWW_ROOT,"",$f["redirect_url"]);
				}
				
				sqlquery("UPDATE bigtree_404s SET requests = (requests + 1) WHERE = '".$f["id"]."'");
				BigTree::redirect($redirect,"301");
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
			Function: makeSecure
				Forces the site into Secure mode.
				When Secure mode is enabled, BigTree will enforce the user being at HTTPS and will rewrite all insecure resources (like CSS, JavaScript, and images) to use HTTPS.
		*/
		
		function makeSecure() {
			if (!$_SERVER["HTTPS"]) {
				BigTree::redirect("https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],"301");
			}
			$this->Secure = true;
		}
		
		/*
			Function: replaceHardRoots
				Replaces all hard roots in a URL with relative ones (i.e. {wwwroot}).

			Parameters:
				string - A string with hard roots.

			Returns:
				A string with relative roots.
		*/

		function replaceHardRoots($string) {
			return str_replace(array(ADMIN_ROOT,WWW_ROOT,STATIC_ROOT),array("{adminroot}","{wwwroot}","{staticroot}"),$string);
		}

		/*
			Function: replaceInternalPageLinks
				Replaces the internal page links in an HTML block with hard links.
			
			Parameters:
				html - An HTML block
			
			Returns:
				An HTML block with links hard-linked.
		*/
		
		function replaceInternalPageLinks($html) {
			$drop_count = 0;
			if (!trim($html)) {
				return "";
			}
			
			if (substr($html,0,6) == "ipl://") {
				$html = $this->getInternalPageLink($html);
			} else {
				$html = $this->replaceRelativeRoots($html);
				$html = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\:\/\.\?\=\-]*)"^',create_function('$matches','global $cms; return \'="\'.$cms->getInternalPageLink($matches[1]).\'"\';'),$html);
			}

			return $html;
		}
		
		/*
			Function: replaceRelativeRoots
				Replaces all relative roots in a URL (i.e. {wwwroot}) with hard links.

			Parameters:
				string - A string with relative roots.

			Returns:
				A string with hard links.
		*/

		function replaceRelativeRoots($string) {
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

		function urlify($title) {
			$accent_match = array('Â', 'Ã', 'Ä', 'À', 'Á', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
			$accent_replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'B', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');

			$title = str_replace($accent_match, $accent_replace, $title);
			$title = htmlspecialchars_decode($title);
			$title = strtolower(preg_replace('/\s/', '-',preg_replace('/[^a-zA-Z0-9\s\-\_]+/', '',trim($title))));
			$title = str_replace("--","-",$title);
	
			return $title;
		}
	}
?>