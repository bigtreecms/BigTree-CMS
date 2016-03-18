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
				$this->External = Link::decode($page["external"]);
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
			Function: allIDs
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/

		static function allIDs() {
			return BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
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
			$children = BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived != 'on'", $page_id);
			foreach ($children as $child_id) {
				AuditTrail::track("bigtree_pages",$child_id,"archived-inherited");
				$this->archiveChildren($child_id);
			}

			// Archive this level
			BigTreeCMS::$DB->query("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' 
								WHERE parent = ? AND archived != 'on'", $page_id);
		}

		/*
			Function: auditAdminLinks
				Gets a list of pages that link back to the admin.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of pages that link to the admin.
		*/

		static function auditAdminLinks($return_arrays = false) {
			global $bigtree;

			$admin_root = BigTreeCMS::$DB->escape($bigtree["config"]["admin_root"]);
			$partial_root = BigTreeCMS::$DB->escape(str_replace($bigtree["config"]["www_root"],"{wwwroot}",$bigtree["config"]["admin_root"]));

			$pages = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_pages 
												WHERE resources LIKE '%$admin_root%' OR 
													  resources LIKE '%$partial_root%' OR
													  REPLACE(resources,'{adminroot}js/embeddable-form.js','') LIKE '%{adminroot}%'
												ORDER BY nav_title ASC");

			if (!$return_arrays) {
				foreach ($pages as &$page) {
					$page = new Page($page);
				}
			}

			return $pages;
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


		/*
			Function: deleteDraft
				Deletes the pending draft of the page.
		*/

		function deleteDraft() {
			// Get the draft copy's ID
			$draft_id = BigTreeCMS::$DB->fetchSingle("SELECT id FROM bigtree_pending_changes 
													  WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);

			// Delete draft copy
			BigTreeCMS::$DB->delete("bigtree_pending_changes",$draft_id);

			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages",$this->ID,"deleted-draft");
			AuditTrail::track("bigtree_pending_changes",$draft_id,"deleted");
		}

		/*
			Function: deleteRevision
				Deletes one of the page's revisions.

			Parameters:
				id - The page reversion id.
		*/

		function deleteRevision($id) {
			// Delete the revision
			BigTreeCMS::$DB->delete("bigtree_page_revisions",$id);

			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages",$this->ID,"deleted-revision");
			AuditTrail::track("bigtree_page_revisions",$id,"deleted");
		}

		/*
			Function: getArchivedChildren
				Returns an alphabetic array of archived child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/

		function getArchivedChildren($return_arrays = false) {
			$children = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_pages WHERE parent = '$parent' AND archived = 'on' 
												   ORDER BY nav_title ASC");

			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}

			return $children;
		}

		/*
			Function: getAlertsForUser
				Gets a list of pages with content older than their Max Content Age that a user follows.

			Parameters:
				user - The user id to pull alerts for or a user entry

			Returns:
				An array of arrays containing a page title, path, and id.
		*/

		static function getAlertsForUser($user) {
			$user = new BigTree\User($user);

			// Alerts is empty, nothing to check
			$user->Alerts = array_filter((array) $user->Alerts);
			if (!$user->Alerts) {
				return array();
			}

			// If we care about the whole tree, skip the madness.
			if ($user->Alerts[0] == "on") {
				return BigTreeCMS::$DB->fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age
												  FROM bigtree_pages 
												  WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
												  ORDER BY current_age DESC");
			} else {
				// We're going to generate a list of pages the user cares about first to get their paths.
				foreach ($user->Alerts as $alert => $status) {
					$where[] = "id = '".BigTreeCMS::$DB->escape($alert)."'";
				}

				// Now from this we'll build a path query
				$path_query = array();
				$path_strings = BigTreeCMS::$DB->fetchAllSingle("SELECT path FROM bigtree_pages WHERE ".implode(" OR ",$where));
				foreach ($path_strings as $path) {
					$path = BigTreeCMS::$DB->escape($path);
					$path_query[] = "path = '$path' OR path LIKE '$path/%'";
				}

				// Only run if the pages requested still exist
				if (count($path_query)) {
					// Find all the pages that are old that contain our paths
					return BigTreeCMS::$DB->fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age 
													  FROM bigtree_pages 
													  WHERE max_age > 0 AND (".implode(" OR ",$path_query).") AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
													  ORDER BY current_age DESC");
				}
			}

			return array();
		}

		/*
			Function: getChildren
				Returns an array of non-archived child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.
				sort - Sort order (defaults to "nav_title ASC")

			Returns:
				An array of Page entries.
		*/

		function getChildren($return_arrays = false, $sort = "nav_title ASC") {
			$children = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived != 'on' ORDER BY $sort", $this->ID);

			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}

			return $children;
		}

		/*
			Function: getHiddenChildren
				Returns an alphabetic array of hidden child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/

		function getHiddenChildren($return_arrays = false) {
			$children = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND in_nav = '' AND archived != 'on' 
												   ORDER BY nav_title ASC", $this->ID);

			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}

			return $children;
		}

		/*
			Function: getLineage
				Returns all the ids of pages above this page not including the homepage.
			
			Returns:
				Array of IDs
		*/
		
		function getLineage() {
			$parents = array();

			$page = $this->ID;
			while ($page = BigTreeCMS::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page)) {
				$parents[] = $page;
			}

			return $parents;
		}

		/*
			Function: getPendingChange
				Returns an array of pending changes for the page.

			Returns:
				A PendingChange object.
		*/

		static function getPendingChange() {
			$change = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);
			if (!$change) {
				return false;
			}

			return new PendingChange($change);
		}

		/*
			Function: getRevision
				Returns a revision of the page.

			Parameters:
				id - The id of the page revision to apply.

			Returns:
				A duplicate Page object with changes applied.
		*/

		static function getRevision($id) {
			$revision = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_page_revisions WHERE id = ?", $id);

			// Get original page
			$page = new Page($revision["page"]);

			$page->External = Link::decode($revision["external"]);
			$page->MetaDescription = $revision["meta_description"];
			$page->NewWindow = $revision["new_window"] ? true : false;
			$page->Resources = array_filter((array) @json_decode($revision["resources"],true));
			$page->Revision = new stdObject;
			$page->Template = $revision["template"];
			$page->Title = $revision["title"];

			$this->Revision->Author = $revision["author"];
			$this->Revision->Description = $revision["saved_description"];
			$this->Revision->Saved = $revision["saved"] ? true : false;
			$this->Revision->UpdatedAt = $revision["updated_at"];

			return $page;
		}

		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the page

			Parameters:
				user - Optional User object to check permissions for (defaults to logged in user)
			
			Returns:
				A permission level ("p" for publisher, "e" for editor, "n" for none)
		*/

		function getUserAccessLevel($user = false) {
			// Default to logged in user
			if ($user == false) {
				global $admin;
			
				// Make sure a user is logged in
				if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
					trigger_error("Property UserAccessLevel not available outside logged-in user context.");
					return false;
				}

				$user = $admin;
			}

			// See if the user is an administrator, if so we can skip permissions.
			if ($user->Level > 0) {
				return "p";
			}

			// See if this page has an explicit permission set and return it if so.
			$explicit_permission = $user->Permissions["page"][$this->ID];
			if ($explicit_permission == "n") {
				return false;
			} elseif ($explicit_permission && $explicit_permission != "i") {
				return $explicit_permission;
			}

			// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
			$page_parent = $this->Parent;
			$parent_permission = $user->Permissions["page"][$page_parent];
			while ((!$parent_permission || $parent_permission == "i") && $page_parent) {
				$parent_id = BigTreeCMS::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page_parent);
				$parent_permission = $user->Permissions["page"][$parent_id];
			}

			// If no permissions are set on the page (we hit page 0 and still nothing) or permission is "n", return not allowed.
			if (!$parent_permission || $parent_permission == "i" || $parent_permission == "n") {
				return false;
			}

			// Return whatever we found.
			return $parent_permission;
		}

		/*
			Function: getUserCanModifyChildren
				Checks whether the logged in user can modify all child pages.
				Assumes we already know that we're a publisher of the parent.

			Returns:
				true if the user can modify all the page children, otherwise false.
		*/

		function getUserCanModifyChildren() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
				trigger_error("Property UserCanModifyChildren not available outside logged-in user context.");
				return false;
			}

			if ($admin->Level > 0) {
				return true;
			}

			$path = BigTreeCMS::$DB->escape($page["path"]);
			$descendant_ids = BigTreeCMS::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE '$path%'");

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
			Function: getVisibleChildren
				Returns a list children of the page that are in navigation.
			
			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page objects.
		*/

		static function getVisibleChildren($return_arrays = false) {
			$children = static::$DB->fetchAll("SELECT * FROM bigtree_pages WHERE parent = '$parent' AND in_nav = 'on' AND archived != 'on' 
											   ORDER BY position DESC, id ASC");
			
			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}

			return $children;
		}

		/*
			Function: regeneratePath
				Calculates the full navigation path for the page, sets $this->Path, and returns the path.

			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		function regeneratePath($id = false, $path = array()) {
			if (!$id) {
				$id = $this->ID;
			}

			$page_info = BigTreeCMS::$DB->fetch("SELECT route, parent FROM bigtree_pages WHERE id = ?", $id);
			$path[] = $page_info["route"];
			
			// If we have a higher page, keep recursing up
			if ($page_info["parent"]) {
				return $this->regeneratePath($page_info["parent"],$path);
			}

			// Reverse since we started with the deepest level but want the inverse
			$this->Path = implode("/",array_reverse($path));

			return $this->Path;
		}
	}