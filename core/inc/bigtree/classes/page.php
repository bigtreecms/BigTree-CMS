<?php
	/*
		Class: BigTree\Page
			Provides an interface for BigTree pages.
	*/

	namespace BigTree;
	
	use BigTree;
	use BigTreeAdmin;
	use BigTreeCMS;

	class Page {

		static $Table = "bigtree_pages";

		protected $CreatedAt;
		protected $ID;
		protected $LastEditedBy;
		protected $UpdatedAt;

		public $AnalyticsPageViews;
		public $Archived;
		public $ArchivedInherited;
		public $ExpireAt;
		public $External;
		public $InNav;
		public $MaxAge;
		public $MetaDescription;
		public $MetaKeywords;
		public $NavigationTitle;
		public $NewWindow;
		public $Parent;
		public $Path;
		public $Position;
		public $PublishAt;
		public $Resources;
		public $Route;
		public $SEOInvisible;
		public $Template;
		public $Title;
		public $Trunk;

		/*
			Constructor:
				Builds a Page object referencing an existing database entry.

			Parameters:
				page - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($page) {
			// Passing in just an ID
			if (!is_array($page)) {
				$page = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);
			}

			// Bad data set
			if (!is_array($page)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				// Protected vars first
				$this->CreatedAt = $page["created_at"];
				$this->ID = $page["id"];
				$this->LastEditedBy = $page["last_edited_by"];
				$this->UpdatedAt = $page["updated_at"];

				// Public vars
				$this->AnalyticsPageViews = $page["ga_page_views"];
				$this->Archived = $page["archived"] ? true : false;
				$this->ArchivedInherited = $page["archived_inherited"] ? true : false;
				$this->ExpireAt = $page["expire_at"] ?: false;
				$this->External = $page["external"];
				$this->InNav = $page["in_nav"] ? true : false;
				$this->MetaDescription = $page["meta_description"];
				$this->MetaKeywords = $page["meta_keywords"];
				$this->NavigationTitle = $page["nav_title"];
				$this->NewWindow = $page["new_window"] ? true : false;
				$this->Parent = $page["parent"];
				$this->Path = $page["path"];
				$this->Position = $page["position"];
				$this->PublishAt = $page["publish_at"] ?: false;
				$this->Resources = array_filter((array) @json_decode($page["resources"],true));
				$this->Route = $page["route"];
				$this->SEOInvisible = $page["seo_invisible"] ? true : false;
				$this->Template = $page["template"];
				$this->Title = $page["title"];
				$this->Trunk = $page["trunk"];	
			}
		}

		/*
			Get Magic Method:
				Allows retrieval of the write-protected ID property.
		*/

		function __get($property) {
			// Read-only properties that require a lot of work, stored as protected methods
			if ($property == "UserAccessLevel") {
				return $this->_getUserAccessLevel();
			}
			if ($property == "UserCanModifyChildren") {
				return $this->_getUserCanModifyChildren();
			}

			return parent::__get($property);
		}

		// $this->UserAccessLevel
		protected function _getUserAccessLevel() {
			global $admin;

			// See if the user is an administrator, if so we can skip permissions.
			if ($admin->Level > 0) {
				return "p";
			}

			// See if this page has an explicit permission set and return it if so.
			$explicit_permission = $admin->Permissions["page"][$page];
			if ($explicit_permission == "n") {
				return false;
			} elseif ($explicit_permission && $explicit_permission != "i") {
				return $explicit_permission;
			}

			// We're now assuming that this page should inherit permissions from farther up the tree, so let's grab the first parent.
			$page_parent = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page);

			// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
			$parent_permission = $admin->Permissions["page"][$page_parent];
			while ((!$parent_permission || $parent_permission == "i") && $page_parent) {
				$parent_id = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page_parent);
				$parent_permission = $admin->Permissions["page"][$parent_id];
			}

			// If no permissions are set on the page (we hit page 0 and still nothing) or permission is "n", return not allowed.
			if (!$parent_permission || $parent_permission == "i" || $parent_permission == "n") {
				return false;
			}

			// Return whatever we found.
			return $parent_permission;
		}

		// $this->UserCanModifyChildren
		protected function _getUserCanModifyChildren() {
			global $admin;

			if ($admin->Level > 0) {
				return true;
			}

			$path = static::$DB->escape($page["path"]);
			$descendant_ids = static::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE '$path%'");

			// Check all the descendants for an explicit "no" or "editor" permission
			foreach ($descendant_ids as $id) {
				$permission = $admin->Permissions["page"][$id];
				if ($permission == "n" || $permission == "e") {
					return false;
				}
			}

			return true;
		}

		/*
			Function: archive
				Archives the page and the page's children.

			See Also:
				<archiveChildren>
		*/

		function archive() {
			// Archive the page and the page children
			BigTreeCMS::$DB->update("bigtree_pages",$page,array("archived" => "on"));
			$this->archiveChildren();

			// Track
			AuditTrail::track("bigtree_pages",$page,"archived");
		}

		/*
			Function: archiveChildren
				Archives the page's children and sets the archive status to inherited.

			See Also:
				<archivePage>
		*/

		function archiveChildren($recursion = false) {
			$page_id = $recursion ?: $this->ID;

			// Track and recursively archive
			$children = static::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived != 'on'", $page_id);
			foreach ($children as $child_id) {
				AuditTrail::track("bigtree_pages",$child_id,"archived-inherited");
				$this->archiveChildren($child_id);
			}

			// Archive this level
			static::$DB->query("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' 
								WHERE parent = ? AND archived != 'on'", $page_id);
		}

		/*
			Function: create
				Creates a page.

			Parameters:
				trunk - Trunk status (true or false)
				parent - Parent page ID
				in_nav - In navigation (true or false)
				nav_title - Navigation title
				title - Page title
				route - Page route (leave empty to auto generate)
				meta_description - Page meta description
				seo_invisible - Pass "X-Robots-Tag: noindex" header (true or false)
				template - Page template ID
				external - External link (or empty)
				new_window - Open in new window from nav (true or false)
				fields - Array of page data
				publish_at - Publish time (or false for immediate publishing)
				expire_at - Expiration time (or false for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (0 for no max)
				tags - An array of tags to apply to the page (optional)

			Returns:
				A Page object.
		*/

		function create($trunk,$parent,$in_nav,$nav_title,$title,$route,$meta_description,$seo_invisible,$template,$external,$new_window,$fields,$publish_at,$expire_at,$max_age,$tags = array()) {
			global $admin;

			// Clean up either their desired route or the nav title
			$route = BigTreeCMS::urlify($route ?: $nav_title);

			// Make sure route isn't longer than 250 characters
			$route = substr($route,0,250);

			// We need to figure out a unique route for the page.  Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists(SERVER_ROOT."site/".$route."/")) {
					$route = $original_route."-".$x;
					$x++;
				}
				while (in_array($route,BigTreeAdmin::$ReservedTLRoutes)) {
					$route = $original_route."-".$x;
					$x++;
				}
			}

			// Make sure it doesn't have the same route as any of its siblings.
			$route = BigTreeCMS::$DB->unique("bigtree_pages","route",$route,array("parent" => $parent),true);

			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = BigTreeCMS::$DB->fetchSingle("SELECT `path` FROM bigtree_pages WHERE id = ?", $parent)."/".$route;
			} else {
				$path = $route;
			}

			// Set the trunk flag back to no if the user isn't a developer
			$trunk = ($trunk ? "on" : "");

			// Create the page
			$id = BigTreeCMS::$DB->insert("bigtree_pages",array(
				"trunk" => $trunk,
				"parent" => $parent,
				"nav_title" => BigTree::safeEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"in_nav" => ($in_nav ? "on" : ""),
				"title" => BigTree::safeEncode($title),
				"template" => $template,
				"external" => ($external ? BigTree\Link::encode($external) : ""),
				"new_window" => ($new_window ? "on" : ""),
				"resources" => $resources,
				"meta_keywords" => BigTree::safeEncode($meta_keywords),
				"meta_description" => BigTree::safeEncode($meta_description),
				"seo_invisible" => ($seo_invisible ? "on" : ""),
				"last_edited_by" => (get_class($admin) == "BigTreeAdmin") ? $admin->ID : null,
				"created_at" => "NOW()",
				"publish_at" => ($publish_at ? date("Y-m-d",strtotime($publish_at)) : null),
				"expire_at" => ($expire_at ? date("Y-m-d",strtotime($expire_at)) : null),
				"max_age" => intval($max_age)
			));

			// Handle tags
			foreach (array_filter((array)$tags) as $tag) {
				BigTreeCMS::$DB->insert("bigtree_tags_rel",array(
					"table" => "bigtree_pages",
					"entry" => $id,
					"tag" => $tag
				));
			}

			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			BigTreeCMS::$DB->delete("bigtree_route_history",array("old_route" => $path));

			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			BigTreeAdmin::clearCache();

			// Let search engines know this page now exists.
			BigTreeAdmin::pingSearchEngines();

			// Track
			AuditTrail::track("bigtree_pages",$id,"created");
			
			return new Page($id);
		}

		/*
			Function: delete
				Deletes the page and all children.
		*/

		function delete() {
			// Delete the children as well.
			$this->deleteChildren($this->ID);

			BigTreeCMS::$DB->delete("bigtree_pages",$this->ID);
			AuditTrail::track("bigtree_pages",$this->ID,"deleted");
		}

		/*
			Function: deleteChildren
				Deletes the children of the page and recurses downward.

			Parameters:
				recursive_id - The parent ID to delete children for (used for recursing down)
		*/

		function deleteChildren($recursive_id = false) {
			$id = $recursive_id ?: $this->ID;

			$children = BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ?", $id);
			foreach ($children as $child) {
				// Recurse to this child's children
				$this->deletePageChildren($child);

				// Delete and track
				BigTreeCMS::$DB->delete("bigtree_pages",$child);
				AuditTrail::track("bigtree_pages",$child,"deleted-inherited");
			}
		}
	}