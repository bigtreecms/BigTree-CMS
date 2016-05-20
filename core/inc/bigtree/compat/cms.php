<?php
	/*
		Class: BigTreeCMS
			The primary interface to BigTree that is used by the front end of the site for pulling settings, navigation, and page content.
	*/

	use BigTree\SQL;

	class BigTreeCMSBase {

		public static $BreadcrumbTrunk;

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
				require_once(BigTree\Router::getIncludePath("inc/lib/kint/Kint.class.php")); 
			} elseif ($bigtree["config"]["debug"]) {
				error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
				ini_set("display_errors","on");
				require_once(BigTree\Router::getIncludePath("inc/lib/kint/Kint.class.php"));
			} else {
				ini_set("display_errors","off");
			}
		
			// Build caches
			BigTree\Module::buildCaches();
		
			// Lazy loading of modules
			$bigtree["class_list"] = array_merge(BigTree\Module::$ClassCache,array(
				"BigTree" => "inc/bigtree/compat/utils.php",
				"BigTreeAdminBase" => "inc/bigtree/compat/admin.php",
				"BigTreeAutoModule" => "inc/bigtree/compat/auto-modules.php",
				"BigTreeModule" => "inc/bigtree/modules.php",
				"BigTreeFTP" => "inc/bigtree/compat/ftp.php",
				"BigTreeSFTP" => "inc/bigtree/compat/sftp.php",
				"BigTreeUpdater" => "inc/bigtree/compat/updater.php",
				"BigTreeGoogleAnalyticsAPI" => "inc/bigtree/compat/google-analytics.php",
				"BigTreePaymentGateway" => "inc/bigtree/compat/payment-gateway.php",
				"BigTreeUploadService" => "inc/bigtree/compat/storage.php", // Backwards compat
				"BigTreeStorage" => "inc/bigtree/compat/storage.php",
				"BigTreeCloudStorage" => "inc/bigtree/compat/cloud-storage.php",
				"BigTreeGeocoding" => "inc/bigtree/compat/geocoding.php",
				"BigTreeEmailService" => "inc/bigtree/compat/email-service.php",
				"BigTreeTwitterAPI" => "inc/bigtree/compat/twitter.php",
				"BigTreeInstagramAPI" => "inc/bigtree/compat/instagram.php",
				"BigTreeGooglePlusAPI" => "inc/bigtree/compat/google-plus.php",
				"BigTreeYouTubeAPI" => "inc/bigtree/compat/youtube.php",
				"BigTreeFlickrAPI" => "inc/bigtree/compat/flickr.php",
				"BigTreeSalesforceAPI" => "inc/bigtree/compat/salesforce.php",
				"BigTreeDisqusAPI" => "inc/bigtree/compat/disqus.php",
				"BigTreeFacebookAPI" => "inc/bigtree/compat/facebook.php",
				"S3" => "inc/lib/amazon-s3.php",
				"CF_Authentication" => "inc/lib/rackspace/cloud.php",
				"CSSMin" => "inc/lib/CSSMin.php",
				"PHPMailer" => "inc/lib/PHPMailer/class.phpmailer.php",
				"SMTP" => "inc/lib/PHPMailer/class.smtp.php",
				"JShrink" => "inc/lib/JShrink.php",
				"PasswordHash" => "inc/lib/PasswordHash.php",
				"TextStatistics" => "inc/lib/text-statistics.php",
				"lessc" => "inc/lib/less-compiler.php"
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
			echo BigTree\Sitemap::getXML();
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
			return BigTree\Navigation::getLevel($parent, $levels,$follow_module,$only_hidden);
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
			$pageObject = BigTree\Page::getPageDraft($id);
			$page = $pageObject->Array;

			// Changes Applied means the tags are already there
			if ($return_tags && !$pageObject->ChangesApplied) {
				$page["tags"] = $pageObject->Tags;
			}

			// Turn tags into arrays
			if (is_array($page["tags"])) {
				foreach ($page["tags"] as $key => $tag) {
					$page["tags"][$key] = $tag->Array;
					$page["tags"][$key]["tag"] = $page["tags"][$key]["name"];
				}
			}

			// Remove ChangesApplied if it's not true
			if (!$pageObject->ChangesApplied) {
				unset($page["changes_applied"]);
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
			return BigTree\Link::getPreview($id);
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
			return BigTree\Page::allByTags($tags, $only_id ? "id" : "array");
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
			$page = new BigTree\Page($bigtree["page"]);
			return $page->getTopLevelPageID($trunk_as_toplevel);
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
			$page = new BigTree\Page($page);
			return $page->getTopLevelPageID($trunk_as_toplevel);
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
			BigTree\Router::forceHTTPS();
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
