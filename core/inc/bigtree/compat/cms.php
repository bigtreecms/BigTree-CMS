<?php
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	use BigTree\SQL;

	class BigTreeCMSBase {

		public static $BreadcrumbTrunk;
		public static $Secure;

		/*
			Constructor:
				Builds caches, sets up auto loaders, loads required files.
		*/
		
		function __construct() {
			global $bigtree;

			// Turn on debugging if we're in debug mode.
			if ($bigtree["config"]["debug"] === "full") {
				error_reporting(E_ALL);
				ini_set("display_errors","on");
				require_once(BigTree::path("inc/lib/kint/Kint.class.php")); 
			} elseif ($bigtree["config"]["debug"]) {
				error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
				ini_set("display_errors","on");
				require_once(BigTree::path("inc/lib/kint/Kint.class.php"));
			} else {
				ini_set("display_errors","off");
			}

			// Auto load classes	
			spl_autoload_register("BigTree::classAutoLoader");
		
			// Build caches
			BigTree\Module::buildCaches();
		
			// Lazy loading of modules
			$bigtree["class_list"] = array_merge(BigTree\Module::$ClassCache,array(
				"BigTreeAdminBase" => "inc/bigtree/admin.php",
				"BigTreeAutoModule" => "inc/bigtree/auto-modules.php",
				"BigTreeModule" => "inc/bigtree/modules.php",
				"BigTreeFTP" => "inc/bigtree/ftp.php",
				"BigTreeSFTP" => "inc/bigtree/sftp.php",
				"BigTreeUpdater" => "inc/bigtree/Updater.php",
				"BigTreeGoogleAnalyticsAPI" => "inc/bigtree/apis/google-analytics.php",
				"BigTreePaymentGateway" => "inc/bigtree/apis/payment-gateway.php",
				"BigTreeUploadService" => "inc/bigtree/apis/storage.php", // Backwards compat
				"BigTreeStorage" => "inc/bigtree/apis/storage.php",
				"BigTreeCloudStorage" => "inc/bigtree/apis/cloud-storage.php",
				"BigTreeGeocoding" => "inc/bigtree/apis/geocoding.php",
				"BigTreeEmailService" => "inc/bigtree/apis/email-service.php",
				"BigTreeTwitterAPI" => "inc/bigtree/apis/twitter.php",
				"BigTreeInstagramAPI" => "inc/bigtree/apis/instagram.php",
				"BigTreeGooglePlusAPI" => "inc/bigtree/apis/google-plus.php",
				"BigTreeYouTubeAPI" => "inc/bigtree/apis/youtube.php",
				"BigTreeFlickrAPI" => "inc/bigtree/apis/flickr.php",
				"BigTreeSalesforceAPI" => "inc/bigtree/apis/salesforce.php",
				"BigTreeDisqusAPI" => "inc/bigtree/apis/disqus.php",
				"BigTreeYahooBOSSAPI" => "inc/bigtree/apis/yahoo-boss.php",
				"BigTreeFacebookAPI" => "inc/bigtree/apis/facebook.php",
				"S3" => "inc/lib/amazon-s3.php",
				"CF_Authentication" => "inc/lib/rackspace/cloud.php",
				"PHPMailer" => "inc/lib/PHPMailer/class.phpmailer.php",
				"PasswordHash" => "inc/lib/PasswordHash.php"
			));

			// Load everything in the custom extras folder.
			$directory_handle = opendir(SERVER_ROOT."custom/inc/required/");
			$custom_required_includes = array();
			while ($file = readdir($directory_handle)) {
				if (substr($file,0,1) != "." && !is_dir(SERVER_ROOT."custom/inc/required/$file")) {
					$custom_required_includes[] = SERVER_ROOT."custom/inc/required/$file";
				}
			}
			closedir($directory_handle);
			
			foreach ($custom_required_includes as $file) {
				include $file;
			}
		
			foreach (BigTree\Extension::$RequiredFiles as $file) {
				include $file;
			}
		}

		/*
			Function: cacheDelete
				Deletes data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data (if no key is passed, deletes all data for a given identifier)
		*/

		static function cacheDelete($identifier,$key = false) {
			BigTree\Cache::delete($identifier,$key);
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
			return BigTree\Cache::get($identifier,$key,$max_age,$decode);
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
			return BigTree\Cache::put($identifier,$key,$value,$replace);
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
			return BigTree\Cache::putUnique($identifier,$value);
		}
		
		/*
			Function: catch404
				Manually catch and display the 404 page from a routed template; logs missing page with handle404
		*/
		
		static function catch404() {
			BigTree\Redirect::catch404();
		}
		
		/*
			Function: checkOldRoutes
				Checks the old route table, redirects if the page is found.
			
			Parameters:
				path - An array of routes
		*/
		
		static function checkOldRoutes($path) {
			BigTree\Router::checkPathHistory($path);
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
			return BigTree\Page::decodeResources($data);
		}
		
		/*
			Function: drawXMLSitemap
				Outputs an XML sitemap.
		*/
		
		static function drawXMLSitemap() {
			header("Content-type: text/xml");
			echo '<?xml version="1.0" encoding="UTF-8" ?>';
			echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
			
			$pages = SQL::fetchAll("SELECT id,template,external,path FROM bigtree_pages 
									WHERE archived = '' AND (publish_at >= NOW() OR publish_at IS NULL) ORDER BY id ASC");
			foreach ($pages as $page) {
				if ($page["template"] || strpos($page["external"],DOMAIN)) {	
					if (!$page["template"]) {
						$link = static::getInternalPageLink($page["external"]);
					} else {
						$link = WWW_ROOT.$page["path"].(($page["id"] > 0) ? "/" : ""); // Fix sitemap adding trailing slashes to home
					}
					
					echo "<url><loc>".$link."</loc></url>\n";
					
					// Added routed template support
					$module_class = SQL::fetchSingle("SELECT bigtree_modules.class
													  FROM bigtree_templates JOIN bigtree_modules 
													  ON bigtree_modules.id = bigtree_templates.module
													  WHERE bigtree_templates.id = ?", $page["template"]);
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
			return BigTree\Setting::context($id);
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
			return BigTree\Page::getBreadcrumbForPage($bigtree["page"],$ignore_trunk);
		}
		
		/*
			Function: getBreadcrumbByPage
				Returns an array of titles, links, and ids for the pages above the given page.
			
			Parameters:
				page - A page array (containing at least the "path" from the database)
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
				If a trunk is hit, BigTreeCMS::$BreadcrumbTrunk is set to the trunk.
			
			See Also:
				<getBreadcrumb>
		*/
		
		static function getBreadcrumbByPage($page,$ignore_trunk = false) {
			return BigTree\Page::getBreadcrumbForPage($page,$ignore_trunk);
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
			$feed = new BigTree\Feed($item);
			$feed = $feed->Array;
			$feed["options"] = $feed["settings"];
			return $feed;
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
			return static::getFeed(SQL::fetch("SELECT * FROM bigtree_feeds WHERE route = ?",$route));
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
			return BigTree\Link::decode($ipl);
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
			return BigTree\Link::get($id);
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
					$where_parent[] = "parent = '".SQL::escape($p)."'";
				}
				$where_parent = "(".implode(" OR ",$where_parent).")";
			// If it's an integer, let's just pull the children for the provided parent.
			} else {
				$parent = SQL::escape($parent);
				$where_parent = "parent = '$parent'";
			}
			
			$in_nav = $only_hidden ? "" : "on";
			$sort = $only_hidden ? "nav_title ASC" : "position DESC, id ASC";
			
			$children = SQL::fetchAll("SELECT id,nav_title,parent,external,new_window,template,route,path 
									   FROM bigtree_pages
									   WHERE $where_parent AND
									   		 in_nav = '$in_nav' AND
									   		 archived != 'on' AND
									   		 (publish_at <= NOW() OR publish_at IS NULL) AND 
									   		 (expire_at >= NOW() OR expire_at IS NULL) 
									   ORDER BY $sort");
			
			// Wrangle up some kids
			foreach ($children as $child) {
				if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
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
						$where_parent[] = "bigtree_pages.id = '".SQL::escape($p)."'";
					}

					$module_pages = SQL::fetchAll("SELECT bigtree_modules.class,
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
					$module_page = SQL::fetch("SELECT bigtree_modules.class,
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
			return BigTree\Router::routeToPage($path,$previewing);
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
			$page = new BigTree\Page($id,$decode);
			$page = $page->Array;

			// Backwards compatibility stuff
			$page["nav_title"] = $page["navigation_title"];
			
			if ($decode) {
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
				$changes = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $page["id"]);

			// If it's prefixed with a "p" then it's a pending entry.
			} else {
				// Set the page to empty, we're going to loop through the change later and apply the fields.
				$page = array();

				// Get the changes.
				$changes = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `id` = ?",substr($id,1));
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
							$tags[] = SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?",$tag);
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
				$path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?",$id);
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

				$tag_id = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?",$tag);
				if ($tag_id) {
					$related_pages = SQL::fetchAllSingle("SELECT entry FROM bigtree_tags_rel WHERE tag = ? AND `table` = 'bigtree_pages'",$tag_id);

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
			return BigTree\Setting::values($id);
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
			return BigTree\Setting::values($ids);
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
			$tag = new BigTree\Tag($id);
			return $tag->Array;
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
			$tag = BigTree\Tag::getByRoute($route);
			return $tag ? $tag->Array : false;
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
			$page = new BigTree\Page($page,false);
			return $page->Tags;
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
			$template = new BigTree\Template($id);
			$template->Resources = $template->Fields;
			return $template->Array;
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
				$paths[] = "path = '".SQL::escape($path)."'";
			}

			// Get either the trunk or the top level nav id.
			$page = SQL::fetch("SELECT id, trunk, path
								FROM bigtree_pages
								WHERE (".implode(" OR ",$paths).") AND
									  (trunk = 'on' OR parent = '0')
								ORDER BY LENGTH(path) DESC
								LIMIT 1");

			// If we don't want the trunk, look higher
			if ($page["trunk"] && $page["parent"] && !$trunk_as_toplevel) {
				// Get the next item in the path.
				$id = SQL::fetchSingle("SELECT id 
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
			return BigTree\Redirect::handle404($url);
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
			return BigTree\Link::tokenize($string);
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
			return BigTree\Link::decode($html);
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
			return BigTree\Link::detokenize($string);
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
			return BigTree\Link::urlify($title);
		}
	}
