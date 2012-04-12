<?
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	include BigTree::path("inc/bigtree/modules.php");
	include BigTree::path("inc/bigtree/forms.php");

	class BigTreeCMS {
	
		var $iplCache = array();

		/*
			Constructor:
				Builds a flat file module class list so that module classes can be autoloaded instead of always in memory.
		*/
		
		function __construct() {
			// If the cache exists, just use it.
			if (file_exists($GLOBALS["server_root"]."cache/module-class-list.btc")) {
				$items = json_decode(file_get_contents($GLOBALS["server_root"]."cache/module-class-list.btc"),true);
			} else {
				// Get the Module Class List
				$q = sqlquery("SELECT * FROM bigtree_modules");
				$items = array();
				while ($f = sqlfetch($q)) {
					$items[$f["class"]] = $f["route"];
				}
				
				// Cache it so we don't hit the database.
				file_put_contents($GLOBALS["server_root"]."cache/module-class-list.btc",json_encode($items));
			}
			
			$this->ModuleClassList = $items;
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
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_route_history WHERE old_route = '".implode("/",array_slice($path,0,$x))."'"));
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
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: ".$GLOBALS["www_root"].str_replace($old,$new,$_GET["bigtree_htaccess_url"]));
				die();
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
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
			$q = sqlquery("SELECT template,external,path FROM bigtree_pages WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL)");
			while ($f = sqlfetch($q)) {
				if ($f["template"] || strpos($f["external"],$GLOBALS["domain"])) {	
					if (!$f["template"]) {
						if (substr($f["external"],0,6) == "ipl://") {
							$link = $this->getInternalPageLink($f["external"]);
						} else {
							$link = str_replace("{wwwroot}",$GLOBALS["www_root"],$f["external"]);
						}
					} else {
						$link = $GLOBALS["www_root"].$f["path"]."/";
					}
					
					echo "<url><loc>".$link."</loc></url>";
				}
			}
			echo '</urlset>';
			die();
		}
		
		/*
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for pages above the current page.
				If a page variable is passed in, this function will return information on the given page rather than the globalized $page variable generated by the router.
			
			Parameters:
				data - A page array (containing at least the "path" from the database) *(optional)*
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumbByPage>
		*/
		
		function getBreadcrumb($data = false) {
			global $page;
			if (!$data) {
				return $this->getBreadcrumbByPage($page);
			} else {
				return $this->getBreadcrumbByPage($data);
			}
		}
		
		/*
			Function: getBreadcrumbByPage
				Returns an array of titles, links, and ids for the pages above the given page.
			
			Parameters:
				page - A page array (containing at least the "path" from the database) *(optional)*
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumb>
		*/
		
		function getBreadcrumbByPage($page) {
			$bc = array();
			
			// Break up the pieces so we can get each piece of the path individually and pull all the pages above this one.
			$pieces = explode("/",$page["path"]);
			$paths = array();
			$path = "";
			foreach ($pieces as $piece) {
				$path = $path.$piece."/";
				$paths[] = "path = '".mysql_real_escape_string(trim($path,"/"))."'";
			}
			
			// Get all the ancestors, ordered by the page length so we get the oldest first.
			$q = sqlquery("SELECT id,nav_title,path FROM bigtree_pages WHERE (".implode(" OR ",$paths).") ORDER BY LENGTH(path) ASC");
			while ($f = sqlfetch($q)) {
				if ($f["external"] && $f["template"] == "") {
					if (substr($f["external"],0,6) == "ipl://") {
						$f["link"] = $this->getInternalPageLink($f["external"]);
					} else {
						$f["link"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$f["external"]);
					}
				} else {
					$f["link"] = $GLOBALS["www_root"].$f["path"]."/";
				}
				$bc[] = array("title" => stripslashes($f["nav_title"]),"link" => $f["link"],"id" => $f["id"]);
			}
			
			// Check for module breadcrumbs
			$mod = sqlfetch(sqlquery("SELECT bigtree_modules.class FROM bigtree_modules JOIN bigtree_templates ON bigtree_modules.id = bigtree_templates.module WHERE bigtree_templates.id = '".$page["template"]."'"));
			if ($mod["class"]) {
				if (class_exists($m["class"])) {
					@eval('$module = new '.$m["class"].';');
					$bc += $module->getBreadcrumb($page);
				}
			}
			
			return $bc;
		}
		
		/*
			Function: getCallout
				Returns a callout template from the database.
			
			Parameters:
				id - The ID of the callout.
			
			Returns:
				A callout template row from the database.
		*/
		
		function getCallout($id) {
			return sqlfetch(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '$id'"));
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
				$item = mysql_real_escape_string($item);
				$item = sqlfetch(sqlquery("SELECT * FROM bigtree_feeds WHERE id = '$item'"));
			}
			if (!$item) {
				return false;
			}
			$item["options"] = json_decode($item["options"],true);
			if (is_array($item["options"])) {
				foreach ($item["options"] as &$option) {
					$option = str_replace("{wwwroot}",$GLOBALS["www_root"],$option);
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
			$route = mysql_real_escape_string($route);
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
				return str_replace("{wwwroot}",$GLOBALS["www_root"],$ipl);
			}
			$ipl = explode("//",$ipl);
			$navid = $ipl[1];
			$commands = implode("/",json_decode(base64_decode($ipl[2]),true));
			if ($commands && strpos($commands,"?") === false) {
				$commands .= "/";
			}
			
			// See if it's in the cache.
			if (isset($this->iplCache[$navid])) {
				return $this->iplCache[$navid].$commands;
			} else {
				// Get the page's path
				$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".mysql_real_escape_string($navid)."'"));
				// Set the cache
				$this->iplCache[$navid] = $GLOBALS["www_root"].$f["path"]."/";
				return $GLOBALS["www_root"].$f["path"]."/".$commands;
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
			if ($id == 0) {
				return $GLOBALS["www_root"];
			}
			$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".mysql_real_escape_string($id)."'"));
			return $GLOBALS["www_root"].$f["path"]."/";
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
			$nav = array();
			$find_children = array();
			
			// If the parent is an array, this is actually a recursed call.
			// We're finding all the children of all the parents at once -- then we'll assign them back to the proper parent instead of doing separate calls for each.
			if (is_array($parent)) {
				$where_parent = array();
				foreach ($parent as $p) {
					$where_parent[] = "parent = '".mysql_real_escape_string($p)."'";
				}
				$where_parent = "(".implode(" OR ",$where_parent).")";
			// If it's an integer, let's just pull the children for the provided parent.
			} else {
				$parent = mysql_real_escape_string($parent);
				$where_parent = "parent = '$parent'";
			}
			
			$in_nav = $only_hidden ? "" : "on";
			
			$q = sqlquery("SELECT id,nav_title,parent,external,new_window,template,route,path FROM bigtree_pages WHERE $where_parent AND in_nav = '$in_nav' AND archived != 'on' AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL) ORDER BY position DESC, id ASC");
			
			// Wrangle up some kids
			while ($f = sqlfetch($q)) {
				$link = $GLOBALS["www_root"].$f["path"]."/";
				$new_window = false;
				
				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.  Then we'll see if we should open this thing in a new window.
				if ($f["external"] && $f["template"] == "") {
					if (substr($f["external"],0,6) == "ipl://") {
						$link = $this->getInternalPageLink($f["external"]);
					} else {
						$link = str_replace("{wwwroot}",$GLOBALS["www_root"],$f["external"]);
					}
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
						$where_parent[] = "bigtree_pages.id = '".mysql_real_escape_string($p)."'";
					}
					$q = sqlquery("SELECT bigtree_modules.class,bigtree_templates.routed,bigtree_templates.module,bigtree_pages.id,bigtree_pages.path,bigtree_pages.template FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_modules.id = bigtree_templates.module AND (".implode(" OR ",$where_parent).")");
					while ($f = sqlfetch($q)) {
						// If the class exists, instantiate it and call it
						if ($f["class"] && class_exists($f["class"])) {
							@eval('$module = new '.$f["class"].';');
							$modNav = $module->getNav($f);
							// Give the parent back to each of the items it returned so they can be reassigned to the proper parent.
							foreach ($modNav as $item) {
								$item["parent"] = $f["id"];
								unset($item["id"]);
								$nav[] = $item;
							}
						}
					}
				// This is the first iteration.
				} else {
					$f = sqlfetch(sqlquery("SELECT bigtree_modules.class,bigtree_templates.routed,bigtree_templates.module,bigtree_pages.id,bigtree_pages.path,bigtree_pages.template FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_modules.id = bigtree_templates.module AND bigtree_pages.id = '$parent'"));
					// If the class exists, instantiate it and call it.
					if ($f["class"] && class_exists($f["class"])) {
						@eval('$module = new '.$f["class"].';');
						$nav += $module->getNav($f);
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
			
			Returns:
				An array containing the page ID and any additional commands.
		*/
		
		function getNavId($path) {
			$commands = array();
			
			// See if we have a straight up perfect match to the path.
			$spath = implode("/",$path);
			$f = sqlfetch(sqlquery("SELECT bigtree_pages.id,bigtree_templates.routed FROM bigtree_pages LEFT JOIN bigtree_templates ON bigtree_pages.template = bigtree_templates.id WHERE path = '$spath' AND archived = '' AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)"));
			if ($f) {
				return array($f["id"],$commands,$f["routed"]);
			}
			
			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;
			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path)-$x];
				$spath = implode("/",array_slice($path,0,-1 * $x));
				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$f = sqlfetch(sqlquery("SELECT bigtree_pages.id FROM bigtree_pages JOIN bigtree_templates ON bigtree_pages.template = bigtree_templates.id WHERE bigtree_pages.path = '$spath' AND bigtree_pages.archived = '' AND bigtree_templates.routed = 'on' AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)"));
				if ($f) {
					return array($f["id"],array_reverse($commands),"on");
				}
			}
			
			return false;
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
				decode - Whether to decode resources and callouts or not (setting to false saves processing time)
			
			Returns:
				A page array from the database.
		*/
		
		function getPendingPage($id,$decode = true) {
			// If the id starts with "p" the page has no published copy.
			if ($id[0] == "p") {
				$page = array();
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($id,1)."'"));
				$changes = json_decode($f["changes"],true);
				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = $this->getInternalPageLink($val);
					}
					$page[$key] = $val;
				}
			// It has a published copy, grab that first.
			} else {
				$page = $this->getPage($id);
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$id'"));
				if ($f) {
					// Apply each of the changes over the published version.
					$changes = json_decode($f["changes"],true);
					foreach ($changes as $key => $val) {
						if ($key == "external") {
							$val = $this->getInternalPageLink($val);
						}
						$page[$key] = $val;
					}
				}
			}
			
			if ($decode) {
				$page["resources"] = $this->decodeResources($page["resources"]);
				$page["callouts"] = $this->decodeCallouts($page["callouts"]);
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
			if ($id == 0) {
				return $GLOBALS["www_root"];
			} elseif (substr($id,0,1) == "p") {
				return $GLOBALS["www_root"]."_preview-pending/".substr($id,1)."/";
			} else {
				$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".mysql_real_escape_string($id)."'"));
				return $GLOBALS["www_root"]."_preview/".$f["path"]."/";
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
				$tdat = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE tag = '".mysql_real_escape_string($tag)."'"));
				if ($tdat) {
					$q = sqlquery("SELECT * FROM bigtree_tags_rel WHERE tag = '".$tdat["id"]."' AND module = '0'");
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
			global $config;
			$id = mysql_real_escape_string($id);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_settings WHERE id = '$id'"));
			// If the setting is encrypted, we need to re-pull just the value.
			if ($f["encrypted"]) {
				$f = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".mysql_real_escape_string($config["settings_key"])."') AS `value` FROM bigtree_settings WHERE id = '$id'"));
			}
			
			$value = json_decode($f["value"],true);
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
			global $config;
			if (!is_array($ids)) {
				$ids = array($ids);
			}
			$parts = array();
			foreach ($ids as $id) {
				$parts[] = "id = '".mysql_real_escape_string($id)."'";
			}
			$settings = array();
			$q = sqlquery("SELECT * FROM bigtree_settings WHERE (".implode(" OR ",$parts).") ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				// If the setting is encrypted, we need to re-pull just the value.
				if ($f["encrypted"]) {
					$f = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".mysql_real_escape_string($config["settings_key"])."') AS `value` FROM bigtree_settings WHERE id = '".$f["id"]."'"));
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
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel WHERE bigtree_tags_rel.module = '0' AND bigtree_tags_rel.entry = '$page' AND bigtree_tags.id = bigtree_tags_rel.tag ORDER BY bigtree_tags.tag");
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
			$t = sqlfetch(sqlquery("SELECT * FROM bigtree_templates WHERE id = '$id'"));
			if (!$t) {
				return false;
			}
			$t["resources"] = json_decode($t["resources"],true);
			return $t;
		}
		
		/*
			Function: getToplevelNavigationId
				Returns the highest level ancestor for the current page.
			
			Returns:
				The ID of the highest ancestor of the current page.
			
			See Also:
				<getToplevelNavigationIdForPage>
			
		*/
		
		function getTopLevelNavigationId() {
			global $page;
			return $this->getTopLevelNavigationIdForPage($page);
		}
		
		/*
			Function: getToplevelNavigationIdForPage
				Returns the highest level ancestor for a given page.
			
			Parameters:
				page - A page array (containing at least the page's "path").
			
			Returns:
				The ID of the highest ancestor of the given page.
			
			See Also:
				<getToplevelNavigationId>
			
		*/
		
		function getTopLevelNavigationIdForPage($page) {
			$parts = explode("/",$page["path"]);
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE path = '".mysql_real_escape_string($parts[0])."'"));
			return $f["id"];
		}
		
		/*
			Function: handle404
				Handles a 404.
			
			Parameters:
				url - The URL you hit that's a 404.
		*/
		
		function handle404($url) {
			global $www_root;
			
			header("HTTP/1.0 404 Not Found");
			$url = mysql_real_escape_string(rtrim($url,"/"));
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_404s WHERE broken_url = '$url'"));

			if ($f["redirect_url"]) {
				if ($f["redirect_url"] == "/") {
					$f["redirect_url"] = "";
				}
				
				if (substr($f["redirect_url"],0,7) == "http://" || substr($f["redirect_url"],0,8) == "https://") {
					$redirect = $f["redirect_url"];
				} else {
					$redirect = $www_root.str_replace($www_root,"",$f["redirect_url"]);
				}
				
				sqlquery("UPDATE bigtree_404s SET requests = (requests + 1) WHERE = '".$f["id"]."'");
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: $redirect");
				die();
			} else {
				$referer = $_SERVER["HTTP_REFERER"];
				$requester = $_SERVER["REMOTE_ADDR"];

				if ($f) {
					sqlquery("UPDATE bigtree_404s SET requests = (requests + 1) WHERE id = '".$f["id"]."'");
				} else {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`,`requests`) VALUES ('".mysql_real_escape_string(rtrim($_GET["bigtree_htaccess_url"],"/"))."','1')");
				}
				return true;
				define("BIGTREE_DO_NOT_CACHE",true);
			}
			
			return false;
		}
		
		/*
			Function: makeSecure
				Forces the site into Secure mode.
				When Secure mode is enabled, BigTree will enforce the user being at HTTPS and will rewrite all insecure resources (like CSS, JavaScript, and images) to use HTTPS.
		*/
		
		function makeSecure() {
			if ($_SERVER["SERVER_PORT"] == 80) {
				header("Location: ".str_replace("http://","https://",$GLOBALS["www_root"]).$_GET["bigtree_htaccess_url"]);
				die();
			}
			$this->Secure = true;
		}
		
		/*
			Function: replaceInternalPageLinks
				Replaces the page links in an HTML block with soft links (ipl and {wwwroot}).
			
			Parameters:
				html - An HTML block
			
			Returns:
				An HTML block with links soft-linked.
		*/
		
		function replaceInternalPageLinks($html) {
			$drop_count = 0;
			if (!trim($html)) {
				return "";
			}
			
			if (substr($html,0,6) == "ipl://") {
				$html = $this->getInternalPageLink($html);
			} else {
				$html = str_replace(array("{wwwroot}","%7Bwwwroot%7D"),$GLOBALS["www_root"],$html);
				$html = preg_replace_callback('^="(ipl:\/\/[a-zA-Z0-9\:\/\.\?\=\-]*)"^',create_function('$matches','global $cms; return \'="\'.$cms->getInternalPageLink($matches[1]).\'"\';'),$html);
			}

			return $html;
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