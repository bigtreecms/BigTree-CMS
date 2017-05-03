<?php
	/*
		Class: BigTree\Page
			Provides an interface for BigTree pages.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read bool $ChangesApplied
	 * @property-read string $CreatedAt
	 * @property-read int $ID
	 * @property-read int $LastEditedBy
	 * @property-read PendingChange $PendingChange
	 * @property-read int $PendingID
	 * @property-read array $Tags
	 * @property-read string $UpdatedAt
	 * @property-read string $UserAccessLevel
	 */
	
	class Page extends BaseObject {
		
		public static $Table = "bigtree_pages";
		
		protected $ChangesApplied = false;
		protected $CreatedAt;
		protected $ID;
		protected $LastEditedBy;
		protected $PendingID;
		protected $UpdatedAt;
		
		public $AnalyticsPageViews;
		public $Archived;
		public $ArchivedInherited;
		public $ExpireAt;
		public $External;
		public $InNav;
		public $MaxAge;
		public $MetaDescription;
		public $NavigationTitle;
		public $NewWindow;
		public $Parent;
		public $Path;
		public $Position;
		public $PublishAt;
		public $Resources;
		public $Revision;
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
				decode - Whether to decode resource data (true is default, false is faster if resource data isn't needed)
		*/
		
		function __construct($page = null, bool $decode = true) {
			// Allow for loading the root (i.e. -1)
			if ($page === -1 || is_null($page)) {
				$this->ID = -1;
			} else {
				// Passing in just an ID
				if (!is_array($page)) {
					$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);
				}
				
				// Bad data set
				if (!is_array($page)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					// Allow for empty page creation (for creating a page from a pending entry)
					if (count($page) == 1) {
						$this->PendingID = $page["id"];
						
						return;
					}
					
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
					$this->External = $page["external"] ? Link::decode($page["external"]) : "";
					$this->InNav = $page["in_nav"] ? true : false;
					$this->MetaDescription = $page["meta_description"];
					$this->NavigationTitle = $page["nav_title"];
					$this->NewWindow = $page["new_window"] ? true : false;
					$this->Parent = $page["parent"];
					$this->Path = $page["path"];
					$this->Position = $page["position"];
					$this->PublishAt = $page["publish_at"] ?: false;
					$this->Resources = $decode ? Link::decode(array_filter((array) @json_decode($page["resources"], true))) : $page["resources"];
					$this->Route = $page["route"];
					$this->SEOInvisible = $page["seo_invisible"] ? true : false;
					$this->Template = $page["template"];
					$this->Title = $page["title"];
					$this->Trunk = $page["trunk"];
				}
			}
		}
		
		// Array conversion
		function getArray(): array {
			$raw_properties = get_object_vars($this);
			$changed_properties = [];
			
			foreach ($raw_properties as $key => $value) {
				$changed_properties[$this->_camelCaseToUnderscore($key)] = $value;
			}
			
			$changed_properties["nav_title"] = $changed_properties["navigation_title"];
			
			return $changed_properties;
		}
		
		/*
			Function: allByTags
				Returns pages related to the given set of tags.

			Parameters:
				tags - An array of tags to search for.
				type - Whether to return Page objects ("object"), IDs ("id"), or Arrays ("array")

			Returns:
				An array of related pages sorted by relevance (how many tags get matched).
		*/
		
		static function allByTags(array $tags, string $type = "object"): array {
			$results = [];
			$relevance = [];
			
			// Loop through each tag finding related pages
			foreach ($tags as $tag) {
				// In case a whole tag row was passed
				if (is_array($tag)) {
					$tag = $tag["tag"];
				}
				
				$tag_id = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $tag);
				
				if ($tag_id) {
					$related_pages = SQL::fetchAllSingle("SELECT entry FROM bigtree_tags_rel 
														  WHERE tag = ? AND `table` = 'bigtree_pages'", $tag_id);
					
					foreach ($related_pages as $page_id) {
						// If we already have this result, add relevance
						if (in_array($page_id, $results)) {
							$relevance[$page_id]++;
						} else {
							$results[] = $page_id;
							$relevance[$page_id] = 1;
						}
					}
				}
			}
			
			// Sort by most relevant
			array_multisort($relevance, SORT_DESC, $results);
			
			if ($type == "id") {
				return $results;
			}
			
			// Get the actual page data for each result
			$items = [];
			
			foreach ($results as $result) {
				$page = new Page($result);
				
				if ($type == "array") {
					$items[] = $page->Array;
				} else {
					$items[] = $page;
				}
			}
			
			return $items;
		}
		
		/*
			Function: allIDs
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/
		
		static function allIDs(): array {
			return SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
		}
		
		/*
			Function: archive
				Archives the page and the page's children.

			See Also:
				<archiveChildren>
		*/
		
		function archive(): void {
			// Archive the page and the page children
			SQL::update("bigtree_pages", $this->ID, ["archived" => "on"]);
			$this->archiveChildren();
			
			// Track
			AuditTrail::track("bigtree_pages", $this->ID, "archived");
		}
		
		/*
			Function: archiveChildren
				Archives the page's children and sets the archive status to inherited.

			See Also:
				<archivePage>
		*/
		
		function archiveChildren($recursion = false): void {
			$page_id = $recursion ?: $this->ID;
			
			// Track and recursively archive
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived != 'on'", $page_id);
			
			foreach ($children as $child_id) {
				AuditTrail::track("bigtree_pages", $child_id, "archived-inherited");
				$this->archiveChildren($child_id);
			}
			
			// Archive this level
			SQL::query("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' 
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
		
		static function auditAdminLinks(bool $return_arrays = false): array {
			global $bigtree;
			
			$admin_root = SQL::escape($bigtree["config"]["admin_root"]);
			$partial_root = SQL::escape(str_replace($bigtree["config"]["www_root"], "{wwwroot}", $bigtree["config"]["admin_root"]));
			
			$pages = SQL::fetchAll("SELECT * FROM bigtree_pages 
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
				resources - Array of page data
				publish_at - Publish time (or false for immediate publishing)
				expire_at - Expiration time (or false for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (0 for no max)
				tags - An array of tags to apply to the page (optional)

			Returns:
				A Page object.
		*/
		
		static function create(bool $trunk, ?int $parent, bool $in_nav, string $nav_title, string $title, ?string $route,
							   ?string $meta_description, bool $seo_invisible, string $template, bool $external,
							   bool $new_window, array $resources, ?string $publish_at, ?string $expire_at, ?int $max_age,
							   ?array $tags = []): Page {
			// Clean up either their desired route or the nav title
			$route = Link::urlify($route ?: $nav_title);
			
			// Make sure route isn't longer than 250 characters
			$route = substr($route, 0, 250);
			
			// We need to figure out a unique route for the page.  Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;
			
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists(SERVER_ROOT."site/".$route."/")) {
					$route = $original_route."-".$x;
					$x++;
				}
				
				$reserved_routes = Router::getReservedRoutes();
				
				while (in_array($route, $reserved_routes)) {
					$route = $original_route."-".$x;
					$x++;
				}
			}
			
			// Make sure it doesn't have the same route as any of its siblings.
			$route = SQL::unique("bigtree_pages", "route", $route, ["parent" => $parent], true);
			
			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = SQL::fetchSingle("SELECT `path` FROM bigtree_pages WHERE id = ?", $parent)."/".$route;
			} else {
				$path = $route;
			}
			
			// Set the trunk flag back to no if the user isn't a developer
			$trunk = ($trunk ? "on" : "");
			
			// Create the page
			$id = SQL::insert("bigtree_pages", [
				"trunk" => $trunk,
				"parent" => $parent,
				"nav_title" => Text::htmlEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"in_nav" => ($in_nav ? "on" : ""),
				"title" => Text::htmlEncode($title),
				"template" => $template,
				"external" => ($external ? Link::encode($external) : ""),
				"new_window" => ($new_window ? "on" : ""),
				"resources" => $resources,
				"meta_description" => Text::htmlEncode($meta_description),
				"seo_invisible" => ($seo_invisible ? "on" : ""),
				"last_edited_by" => Auth::user()->ID,
				"created_at" => "NOW()",
				"publish_at" => ($publish_at ? date("Y-m-d", strtotime($publish_at)) : null),
				"expire_at" => ($expire_at ? date("Y-m-d", strtotime($expire_at)) : null),
				"max_age" => intval($max_age)
			]);
			
			// Handle tags
			foreach (array_filter((array) $tags) as $tag) {
				SQL::insert("bigtree_tags_rel", [
					"table" => "bigtree_pages",
					"entry" => $id,
					"tag" => $tag
				]);
			}
			
			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			SQL::delete("bigtree_route_history", ["old_route" => $path]);
			
			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			Router::clearCache();
			
			// Let search engines know this page now exists.
			Sitemap::pingSearchEngines();
			
			// Track
			AuditTrail::track("bigtree_pages", $id, "created");
			
			return new Page($id);
		}
		
		/*
			Function: createChangeRequest
				Adds a pending change to the bigtree_pending_changes table for a given page.
				Determines what has changed and only stores the changed fields.

			Parameters:
				page - The page id or pending page id (prefixed with a "p")
				changes - An array of changes
		*/
		
		static function createChangeRequest(string $page, array $changes): int {
			// Get the user creating the change
			$user = Auth::user()->ID;
			
			// Get existing information
			if ($page[0] == "p") {
				// It's still pending
				$pending = true;
				$existing_page = [];
				$existing_pending_change = substr($page, 1);
			} else {
				// It's an existing page
				$pending = false;
				$existing_page = new Page($page);
				$existing_page = $existing_page->Array;
				$existing_pending_change = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes 
															 WHERE `table` = 'bigtree_pages' AND item_id = ?", $page);
			}
			
			// Save tags separately
			$tags = JSON::encode($changes["_tags"], true);
			unset($changes["_tags"]);
			
			// Convert to an IPL
			if (!empty($changes["external"])) {
				$changes["external"] = Link::iplEncode($changes["external"]);
			}
			
			// Set trunk flag
			$changes["trunk"] = !empty($changes["trunk"]) ? "on" : "";
			
			// Set the in_nav flag, since it's not in the post if the checkbox became unclicked
			$changes["in_nav"] = !empty($changes["in_nav"]) ? "on" : "";
			
			// If there's already a change in the queue, update it with this latest info.
			if ($existing_pending_change) {
				// If this is a pending page, just replace all the changes
				if ($pending) {
					$diff = $changes;
					// Otherwise, we need to check what's changed.
				} else {
					// We don't want to indiscriminately put post data in as changes, so we ensure it matches a column in the bigtree_pages table
					$diff = [];
					
					foreach ($changes as $key => $val) {
						if (array_key_exists($key, $existing_page) && $existing_page[$key] != $val) {
							$diff[$key] = $val;
						}
					}
				}
				
				// Update existing draft and track
				SQL::update("bigtree_pending_changes", $existing_pending_change, [
					"changes" => $diff,
					"tags_changes" => $tags,
					"user" => $user
				]);
				
				AuditTrail::track("bigtree_pages", $page, "updated-draft");
				
				return $existing_pending_change;
				
			} else {
				// We're submitting a change to a presently published page with no pending changes.
				$diff = [];
				
				foreach ($changes as $key => $val) {
					if (array_key_exists($key, $existing_page) && $val != $existing_page[$key]) {
						$diff[$key] = $val;
					}
				}
				
				// Create draft and track
				AuditTrail::track("bigtree_pages", $page, "saved-draft");
				
				return SQL::insert("bigtree_pending_changes", [
					"user" => $user,
					"table" => "bigtree_pages",
					"item_id" => $page,
					"changes" => $diff,
					"tags_changes" => $tags,
					"title" => "Page Change Pending"
				]);
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
		
		static function decodeResources(array $data): array {
			if (!is_array($data)) {
				$data = json_decode($data, true);
			}
			
			if (is_array($data)) {
				foreach ($data as $key => $val) {
					// Already an array, decode the whole thing
					if (is_array($val)) {
						$val = Link::decode($val);
					} else {
						// See if it's a JSON string first, if so decode the array
						$decoded_val = json_decode($val, true);
						
						if (is_array($decoded_val)) {
							$val = Link::decode($decoded_val);
							
							// Otherwise it's a string, just replace the {wwwroot} and ipls.
						} else {
							$val = Link::decode($val);
						}
					}
					
					$data[$key] = $val;
				}
			}
			
			return $data;
		}
		
		/*
			Function: delete
				Deletes the page and all children.
		*/
		
		function delete(): ?bool {
			// Delete the children as well.
			$this->deleteChildren($this->ID);
			
			SQL::delete("bigtree_pages", $this->ID);
			AuditTrail::track("bigtree_pages", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: deleteChildren
				Deletes the children of the page and recurses downward.

			Parameters:
				recursive_id - The parent ID to delete children for (used for recursing down)
		*/
		
		function deleteChildren(?int $recursive_id = null): void {
			$id = $recursive_id ?: $this->ID;
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ?", $id);
			
			foreach ($children as $child) {
				// Recurse to this child's children
				$this->deleteChildren($child);
				
				// Delete and track
				SQL::delete("bigtree_pages", $child);
				AuditTrail::track("bigtree_pages", $child, "deleted-inherited");
			}
		}
		
		/*
			Function: deleteDraft
				Deletes the pending draft of the page.
		*/
		
		function deleteDraft(): void {
			// Get the draft copy's ID
			$draft_id = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes 
										  WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);
			
			// Delete draft copy
			SQL::delete("bigtree_pending_changes", $draft_id);
			
			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages", $this->ID, "deleted-draft");
			AuditTrail::track("bigtree_pending_changes", $draft_id, "deleted");
		}
		
		/*
			Function: deleteRevision
				Deletes one of the page's revisions.

			Parameters:
				id - The page reversion id.
		*/
		
		function deleteRevision(int $id): void {
			// Delete the revision
			SQL::delete("bigtree_page_revisions", $id);
			
			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages", $this->ID, "deleted-revision");
			AuditTrail::track("bigtree_page_revisions", $id, "deleted");
		}
		
		/*
			Function: getArchivedChildren
				Returns an alphabetic array of archived child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/
		
		function getArchivedChildren(bool $return_arrays = false): array {
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived = 'on' 
									   ORDER BY nav_title ASC", $this->ID);
			
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
		
		static function getAlertsForUser($user): array {
			$user = new User($user);
			
			// Alerts is empty, nothing to check
			$user->Alerts = array_filter((array) $user->Alerts);
			
			if (!count($user->Alerts)) {
				return [];
			}
			
			// If we care about the whole tree, skip the madness.
			if ($user->Alerts[0] == "on") {
				return SQL::fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age
								      FROM bigtree_pages 
								      WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
								      ORDER BY current_age DESC");
			} else {
				$where = [];
				
				// We're going to generate a list of pages the user cares about first to get their paths.
				foreach ($user->Alerts as $alert => $status) {
					$where[] = "id = '".SQL::escape($alert)."'";
				}
				
				// Now from this we'll build a path query
				$path_query = [];
				$path_strings = SQL::fetchAllSingle("SELECT path FROM bigtree_pages WHERE ".implode(" OR ", $where));
				
				foreach ($path_strings as $path) {
					$path = SQL::escape($path);
					$path_query[] = "path = '$path' OR path LIKE '$path/%'";
				}
				
				// Only run if the pages requested still exist
				if (count($path_query)) {
					// Find all the pages that are old that contain our paths
					return SQL::fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age 
										  FROM bigtree_pages 
										  WHERE max_age > 0 AND (".implode(" OR ", $path_query).") AND 
										        DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
										  ORDER BY current_age DESC");
				}
			}
			
			return [];
		}
		
		/*
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for pages above this page.
			
			Parameters:
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumbByPage>
		*/
		
		function getBreadcrumb(bool $ignore_trunk = false): array {
			return Navigation::getBreadcrumb($this, $ignore_trunk);
		}
		
		/*
			Function: getChangeExists
				Returns whether pending changes exist for the page.

			Returns:
				true or false
		*/
		
		function getChangeExists(): bool {
			return SQL::exists("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $this->ID]);
		}
		
		/*
			Function: getChildren
				Returns an array of non-archived child pages.

			Parameters:
				sort - Sort order (defaults to "nav_title ASC")
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/
		
		function getChildren(string $sort = "nav_title ASC", bool $return_arrays = false): array {
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived != 'on' ORDER BY $sort", $this->ID);
			
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
		
		function getHiddenChildren(bool $return_arrays = false): array {
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND in_nav = '' AND archived != 'on' 
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
				Returns all the ids of pages above the provided page ID not including the homepage.
			
			Parameters:
				page - A Page ID
		
			Returns:
				Array of IDs
		*/
		
		static function getLineage(int $page): array {
			$parents = [];
			
			while ($page = SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page)) {
				$parents[] = $page;
			}
			
			return $parents;
		}
		
		/*
			Function: getPendingChange
				Returns a PendingChange object that applies to this page.

			Returns:
				A PendingChange object.
		*/
		
		function getPendingChange(): ?PendingChange {
			$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);
			
			if (empty($change)) {
				return null;
			}
			
			return new PendingChange($change);
		}
		
		/*
			Function: getPendingChildren
				Returns an array of pending child pages of this page with most recent first.

			Parameters:
				in_nav - true returns pages in navigation, false returns hidden pages

			Returns:
				An array of pending page titles/ids.
		*/
		
		function getPendingChildren(bool $in_nav = true): array {
			$nav = $titles = [];
			$changes = SQL::fetchAll("SELECT * FROM bigtree_pending_changes 
									  WHERE pending_page_parent = ? AND `table` = 'bigtree_pages' AND item_id IS NULL 
									  ORDER BY date DESC", $this->ID);
			
			foreach ($changes as $change) {
				$page = json_decode($change["changes"], true);
				
				// Only get the portion we're asking for
				if (($page["in_nav"] && $in_nav) || (!$page["in_nav"] && !$in_nav)) {
					$page["bigtree_pending"] = true;
					$page["title"] = $page["nav_title"];
					$page["id"] = "p".$change["id"];
					
					$titles[] = $page["nav_title"];
					$nav[] = $page;
				}
			}
			
			// Sort by title
			array_multisort($titles, $nav);
			
			return $nav;
		}
		
		/*
			Function: getPageDraft
				Returns a page along with pending changes applied.

			Parameters:
				id - The ID of the page (or "p" + id for pulling an unpublished page)

			Returns:
				A Page object or false if no page was found.
		*/
		
		static function getPageDraft(string $id): ?Page {
			if (is_numeric($id)) {
				// Numeric id means the page is live.
				$page = new Page($id);
				
				if (!$page) {
					return null;
				}
				
				// Get pending changes for this page.
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes 
									   WHERE `table` = 'bigtree_pages' AND item_id = ?", $page->ID);
				
			} else {
				// If it's prefixed with a "p" then it's a pending entry.
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `id` = ?", substr($id, 1));
				
				if (empty($pending)) {
					return null;
				}
				
				$page = new Page;
			}
			
			// No changes, just return
			if (empty($pending)) {
				return $page;
			}
			
			// Decode the tag changes, apply them back.
			$tags_changes = json_decode($pending["tags_changes"], true);
			
			if (is_array($tags_changes)) {
				$page->setTags($tags_changes);
			}
			
			// Regular changes
			$changes = Link::decode(json_decode($pending["changes"], true));
			
			// Protected vars, force an inheritance
			$draft_data = new \stdClass;
			$draft_data->ChangesApplied = true;
			$draft_data->ChangeID = $pending["id"];
			$draft_data->UpdatedAt = $pending["date"];
			$draft_data->LastEditedBy = $pending["user"] ?: $page->LastEditedBy;
			$page->inherit($draft_data);
			
			// Public vars -- things here only exist if there's been a change so we do the weird isset syntax
			$page->Parent = ($pending["pending_page_parent"] !== null) ? $pending["pending_page_parent"] : $page->Parent;
			
			isset($changes["expire_at"]) ? ($page->ExpireAt = $changes["expire_at"] ?: false) : false;
			isset($changes["external"]) ? ($page->External = $changes["external"]) : false;
			isset($changes["in_nav"]) ? ($page->InNav = $changes["in_nav"] ? true : false) : false;
			isset($changes["meta_description"]) ? ($page->MetaDescription = $changes["meta_description"]) : false;
			isset($changes["nav_title"]) ? ($page->NavigationTitle = $changes["nav_title"]) : false;
			isset($changes["new_window"]) ? ($page->NewWindow = $changes["new_window"] ? true : false) : false;
			isset($changes["path"]) ? ($page->Path = $changes["path"]) : false;
			isset($changes["publish_at"]) ? ($page->PublishAt = $changes["publish_at"] ?: false) : false;
			isset($changes["resources"]) ? ($page->Resources = $changes["resources"]) : false;
			isset($changes["route"]) ? ($page->Route = $changes["route"]) : false;
			isset($changes["seo_invisible"]) ? ($page->SEOInvisible = $changes["seo_invisible"] ? true : false) : false;
			isset($changes["template"]) ? ($page->Template = $changes["template"]) : false;
			isset($changes["title"]) ? ($page->Title = $changes["title"]) : false;
			isset($changes["trunk"]) ? ($page->Trunk = $changes["trunk"]) : false;
			
			return $page;
		}
		
		/*
			Function: getRevision
				Returns a revision of the page.

			Parameters:
				id - The id of the page revision to apply.

			Returns:
				A duplicate Page object with changes applied.
		*/
		
		static function getRevision(int $id): Page {
			$revision = SQL::fetch("SELECT * FROM bigtree_page_revisions WHERE id = ?", $id);
			
			// Get original page
			$page = new Page($revision["page"]);
			
			$page->External = Link::decode($revision["external"]);
			$page->MetaDescription = $revision["meta_description"];
			$page->NewWindow = $revision["new_window"] ? true : false;
			$page->Resources = array_filter((array) @json_decode($revision["resources"], true));
			$page->Revision = new \stdClass;
			$page->Template = $revision["template"];
			$page->Title = $revision["title"];
			
			$page->Revision->Author = $revision["author"];
			$page->Revision->Description = $revision["saved_description"];
			$page->Revision->Saved = $revision["saved"] ? true : false;
			$page->Revision->UpdatedAt = $revision["updated_at"];
			
			return $page;
		}
		
		/*
			Function: getSEORating
				Returns the SEO rating for the page.

			Returns:
				An array of SEO data.
				"score" reflects a score from 0 to 100 points.
				"recommendations" is an array of recommendations to improve SEO score.
				"color" is a color reflecting the SEO score.

				Score Parameters
				- Having a title - 5 points
				- Having a unique title - 5 points
				- Title does not exceed 72 characters and has at least 4 words - 5 points
				- Having a meta description - 5 points
				- Meta description that is less than 165 characters - 5 points
				- Having an h1 - 10 points
				- Having page content - 5 points
				- Having at least 300 words in your content - 15 points
				- Having links in your content - 5 points
				- Having external links in your content - 5 points
				- Having one link for every 120 words of content - 5 points
				- Readability Score - up to 20 points
				- Fresh content - up to 10 points
		*/
		
		function getSEORating(): array {
			if (empty($this->Template) || $this->Template == "!") {
				return ["score" => 100, "recommendations" => [], "color" => "#00CC00"];
			}

			$template = new Template($this->Template);
			$template_fields = [];
			$h1_field = "";
			$body_fields = [];
			
			// Figure out what fields should behave as the SEO body and H1
			if (is_array($template->Fields)) {
				foreach ($template->Fields as $item) {
					if (isset($item["seo_body"]) && $item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if (isset($item["seo_h1"]) && $item["seo_h1"]) {
						$h1_field = $item["id"];
					}
					$template_fields[$item["id"]] = $item;
				}
			}
			
			// Default to page_header and page_content
			if (!$h1_field && $template_fields["page_header"]) {
				$h1_field = "page_header";
			}
			
			if (!count($body_fields) && $template_fields["page_content"]) {
				$body_fields[] = "page_content";
			}
			
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Text.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Maths.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Syllables.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Pluralise.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Resource.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/TextStatistics.php";
			
			$textStats = new \DaveChild\TextStatistics\TextStatistics;
			$recommendations = [];
			
			$score = 0;
			
			// Check if they have a page title.
			if ($this->Title) {
				$score += 5;
				
				// They have a title, let's see if it's unique
				$count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
										   WHERE title = ? AND id != ?", $this->Title, $this->ID);
				if (!$count) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be unique. ".($count - 1)." other page(s) have the same title.";
				}
				
				// Check title length / word count
				$words = $textStats->wordCount($this->Title);
				$length = mb_strlen($this->Title);
				
				// Minimum of 4 words, less than 72 characters
				if ($words >= 4 && $length <= 72) {
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be no more than 72 characters and should contain at least 4 words.";
				}
			} else {
				$recommendations[] = "You should enter a page title.";
			}
			
			// Check for meta description
			if ($this->MetaDescription) {
				$score += 5;
				
				// They have a meta description, let's see if it's no more than 165 characters.
				$meta_length = mb_strlen($this->MetaDescription);
				if ($meta_length <= 165) {
					$score += 5;
				} else {
					$recommendations[] = "Your meta description should be no more than 165 characters.  It is currently $meta_length characters.";
				}
			} else {
				$recommendations[] = "You should enter a meta description.";
			}
			
			// Check for an H1
			if (!$h1_field || !empty($this->Resources[$h1_field])) {
				$score += 10;
			} else {
				$recommendations[] = "You should enter a page header.";
			}
			
			// Check the content!
			if (!count($body_fields)) {
				// If this template doesn't for some reason have a seo body resource, give the benefit of the doubt.
				$score += 65;
			} else {
				$regular_text = "";
				$stripped_text = "";
				
				foreach ($body_fields as $field) {
					if (!is_array($this->Resources[$field])) {
						$regular_text .= $this->Resources[$field]." ";
						$stripped_text .= strip_tags($this->Resources[$field])." ";
					}
				}

				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->wordCount($stripped_text);
					$readability = $textStats->fleschKincaidReadingEase($stripped_text);
					
					if ($readability < 0) {
						$readability = 0;
					}

					$number_of_links = substr_count($regular_text, "<a ");
					$number_of_external_links = substr_count($regular_text, 'href="http://');
					
					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[] = "You should enter at least 300 words of page content.  You currently have ".$words." word(s).";
					}
					
					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[] = "You should have at least one link for every 120 words of page content.  You currently have $number_of_links link(s).  You should have at least ".floor($words / 120).".";
						}

						// See if we have any external links.
						if ($number_of_external_links) {
							$score += 5;
						} else {
							$recommendations[] = "Having an external link helps build Page Rank.";
						}
					} else {
						$recommendations[] = "You should have at least one link in your content.";
					}
					
					// Check on our readability score.
					if ($readability >= 90) {
						$score += 20;
					} else {
						$read_score = round(($readability / 90), 2);
						$recommendations[] = "Your readability score is ".($read_score * 100)."%.  Using shorter sentences and words with fewer syllables will make your site easier to read by search engines and users.";
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[] = "You should enter page content.";
				}
				
				// Check page freshness
				$updated = strtotime($this->UpdatedAt);
				$age = time() - $updated - (60 * 24 * 60 * 60);
				
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30 * 24 * 60 * 60)));
					
					if ($age_score < 0) {
						$age_score = 0;
					}

					$score += $age_score;
					$recommendations[] = "Your content is around ".ceil(2 + ($age / (30 * 24 * 60 * 60)))." months old.  Updating your page more frequently will make it rank higher.";
				} else {
					$score += 10;
				}
			}
			
			$color = "#008000";
			
			if ($score <= 50) {
				$color = Utils::colorMesh("#CCAC00", "#FF0000", 100 - (100 * $score / 50));
			} elseif ($score <= 80) {
				$color = Utils::colorMesh("#008000", "#CCAC00", 100 - (100 * ($score - 50) / 30));
			}
			
			return ["score" => $score, "recommendations" => $recommendations, "color" => $color];
		}
		
		/*
			Function: getTags
				Returns an array of tags for this page.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.
			
			Returns:
				An array of Tag objects.
		*/
		
		function getTags(bool $return_arrays = false): array {
			$tags = SQL::fetchAll("SELECT bigtree_tags.*
								   FROM bigtree_tags JOIN bigtree_tags_rel 
								   ON bigtree_tags.id = bigtree_tags_rel.tag 
								   WHERE bigtree_tags_rel.`table` = 'bigtree_pages' AND bigtree_tags_rel.entry = ?
								   ORDER BY bigtree_tags.tag", $this->ID);
			
			if (!$return_arrays) {
				foreach ($tags as &$tag) {
					$tag = new Tag($tag);
				}
			}
			
			return $tags;
		}
		
		/*
			Function: getTopLevelPageID
				Returns the highest level ancestor's ID for the page.
			
			Parameters:
				trunk_as_top_level - Treat a trunk as top level navigation instead of a new "site" (will return the trunk instead of the first nav item below the trunk if encountered) - defaults to false
			
			Returns:
				The ID of the highest ancestor of the given page.
			
			See Also:
				<getToplevelNavigationId>
			
		*/
		
		function getTopLevelPageID(bool $trunk_as_top_level = false): int {
			$paths = [];
			$path = "";
			$parts = explode("/", $this->Path);
			
			foreach ($parts as $part) {
				$path .= "/".$part;
				$path = ltrim($path, "/");
				$paths[] = "path = '".SQL::escape($path)."'";
			}
			
			// Get either the trunk or the top level nav id.
			$page = SQL::fetch("SELECT id, trunk, path FROM bigtree_pages
								WHERE (".implode(" OR ", $paths).") AND (trunk = 'on' OR parent = '".BIGTREE_SITE_TRUNK."')
								ORDER BY LENGTH(path) DESC LIMIT 1");
			
			// If we don't want the trunk, look higher
			if ($page["trunk"] && $page["parent"] && !$trunk_as_top_level) {
				// Get the next item in the path.
				$id = SQL::fetchSingle("SELECT id FROM bigtree_pages 
										WHERE (".implode(" OR ", $paths).") AND LENGTH(path) < ".strlen($page["path"])." 
										ORDER BY LENGTH(path) ASC LIMIT 1");
				if ($id) {
					return $id;
				}
			}
			
			return $page["id"];
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the page

			Parameters:
				user - Optional User object to check permissions for (defaults to logged in user)
			
			Returns:
				A permission level ("p" for publisher, "e" for editor, null for none)
		*/
		
		function getUserAccessLevel(?User $user = null): ?string {
			return Auth::user($user)->getAccessLevel($this);
		}
		
		/*
			Function: getUserCanModifyChildren
				Checks whether the logged in user can modify all child pages.
				Assumes we already know that we're a publisher of the parent.

			Returns:
				true if the user can modify all the page children, otherwise false.
		*/
		
		function getUserCanModifyChildren(): bool {
			// Make sure a user is logged in
			if (is_null(Auth::user()->ID)) {
				trigger_error("Property UserCanModifyChildren not available outside logged-in user context.");
				
				return false;
			}
			
			if (Auth::user()->Level > 0) {
				return true;
			}
			
			$path = SQL::escape($this->Path);
			$descendant_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE '$path%'");
			
			// Check all the descendants for an explicit "no" or "editor" permission
			foreach ($descendant_ids as $id) {
				$permission = Auth::user()->Permissions["page"][$id];
				
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
		
		function getVisibleChildren(bool $return_arrays = false): array {
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND in_nav = 'on' AND archived != 'on' 
									   ORDER BY position DESC, id ASC", $this->ID);
			
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
		
		function regeneratePath(?int $id = null, array $path = []): string {
			if (is_null($id)) {
				$id = $this->ID;
			}
			
			$page_info = SQL::fetch("SELECT route, parent FROM bigtree_pages WHERE id = ?", $id);
			$path[] = $page_info["route"];
			
			// If we have a higher page, keep recursing up
			if ($page_info["parent"] > 0) {
				return $this->regeneratePath($page_info["parent"], $path);
			}
			
			// Reverse since we started with the deepest level but want the inverse
			$this->Path = implode("/", array_reverse($path));
			
			return $this->Path;
		}
		
		/*
			Function: search
				Searches for pages based on the provided fields.

			Parameters:
				query - Query string to search against.
				fields - Fields to search.
				max - Maximum number of results to return.
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page objects.
		*/
		
		static function search(string $query, array $fields = ["nav_title"], int $max = 10,
							   bool $return_arrays = false): array {
			// Since we're in JSON we have to do stupid things to the /s for URL searches.
			$query = str_replace('/', '\\\/', $query);
			
			$terms = explode(" ", $query);
			$where_parts = ["archived != 'on'"];
			
			foreach ($terms as $term) {
				$term = SQL::escape($term);
				
				$or_parts = [];
				foreach ($fields as $field) {
					$or_parts[] = "`$field` LIKE '%$term%'";
				}
				
				$where_parts[] = "(".implode(" OR ", $or_parts).")";
			}
			
			$pages = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE ".implode(" AND ", $where_parts)." 
									ORDER BY nav_title LIMIT $max");
			
			if (!$return_arrays) {
				foreach ($pages as &$page) {
					$page = new Page($page);
				}
			}
			
			return $pages;
		}
		
		/*
			Function: unarchive
				Unarchives the page and all its children that inherited archived status.
		*/
		
		function unarchive() {
			SQL::update("bigtree_pages", $this->ID, ["archived" => ""]);
			$this->unarchiveChildren();
			
			AuditTrail::track("bigtree_pages", $this->ID, "unarchived");
		}
		
		/*
			Function: unarchiveChildren
				Unarchives the page's children that have the archived_inherited status.
		*/
		
		function unarchiveChildren(?int $id = null) {
			// Allow for recursion
			if (is_null($id)) {
				$id = $this->ID;
			}
			
			$child_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived_inherited = 'on'", $id);
			
			foreach ($child_ids as $child_id) {
				AuditTrail::track("bigtree_pages", $child_id, "unarchived-inherited");
				$this->unarchiveChildren($child_id);
			}
			
			// Unarchive this level
			SQL::query("UPDATE bigtree_pages SET archived = '', archived_inherited = '' 
						WHERE parent = ? AND archived_inherited = 'on'", $id);
		}
		
		/*
			Function: uncache
				Removes any static cache copies of this page.
		*/
		
		function uncache() {
			FileSystem::deleteFile(md5(json_encode(["bigtree_htaccess_url" => $this->Path])).".page");
			FileSystem::deleteFile(md5(json_encode(["bigtree_htaccess_url" => $this->Path."/"])).".page");
		}
		
		/*
			Function: save
				Saves and validates object properties back to the database.
		*/
		
		function save(): ?bool {
			// Explicitly set to -1 in the constructor for null pages
			if ($this->ID === -1) {
				$new = static::create(
					$this->Trunk,
					$this->Parent,
					$this->InNav,
					$this->NavigationTitle,
					$this->Title,
					$this->Route,
					$this->MetaDescription,
					$this->SEOInvisible,
					$this->Template,
					$this->External,
					$this->NewWindow,
					$this->Resources,
					$this->PublishAt,
					$this->ExpireAt,
					$this->MaxAge
				);
				$this->inherit($new);
				
				return true;
			}
			
			// Homepage must have no route
			if ($this->ID == 0) {
				$this->Route = "";
				$this->Parent = -1;
			} else {
				// Get a unique route
				$original_route = $route = Link::urlify($this->Route);
				$x = 2;
				
				// Reserved paths.
				if ($this->Parent == 0) {
					while (file_exists(SERVER_ROOT."site/".$route."/")) {
						$route = $original_route."-".$x;
						$x++;
					}
					$reserved_routes = Router::getReservedRoutes();
					while (in_array($route, $reserved_routes)) {
						$route = $original_route."-".$x;
						$x++;
					}
				}
				
				// Make sure route isn't longer than 250
				$route = substr($route, 0, 250);
				
				// Existing pages.
				while (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
										 WHERE `route` = ? AND parent = ? AND id != ?", $route, $this->Parent, $this->ID)) {
					$route = $original_route."-".$x;
					$x++;
				}
				
				$this->Route = $route;
			}
			
			// Update the path in case the parent changed
			$original_path = $this->Path;
			$this->regeneratePath();
			
			// Remove old paths from the redirect list, add a new redirect and update children
			if ($this->Path != $original_path) {
				$this->updateChildrenPaths();
				
				SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $this->Path, $original_path);
				SQL::insert("bigtree_route_history", [
					"old_route" => $original_path,
					"new_route" => $this->Path
				]);
			}
			
			SQL::update("bigtree_pages", $this->ID, [
				"trunk" => $this->Trunk ? "on" : "",
				"parent" => $this->Parent,
				"in_nav" => $this->InNav ? "on" : "",
				"nav_title" => Text::htmlEncode($this->NavigationTitle),
				"title" => Text::htmlEncode($this->Title),
				"path" => $this->Path,
				"route" => $this->Route,
				"meta_description" => Text::htmlEncode($this->MetaDescription),
				"seo_invisible" => $this->SEOInvisible ? "on" : "",
				"template" => $this->Template,
				"external" => $this->External ? Link::encode($this->External) : "",
				"new_window" => $this->NewWindow ? "on" : "",
				"resources" => (array) $this->Resources,
				"publish_at" => $this->PublishAt ?: null,
				"expire_at" => $this->ExpireAt ?: null,
				"max_age" => $this->MaxAge ?: 0,
				"last_edited_by" => Auth::user()->ID ?: $this->LastEditedBy
			]);
			
			// Remove any pending drafts
			SQL::delete("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $this->ID]);
			
			// Handle tags
			SQL::delete("bigtree_tags_rel", ["table" => "bigtree_pages", "entry" => $this->ID]);
			
			foreach ($this->Tags as $tag) {
				SQL::insert("bigtree_tags_rel", [
					"table" => "bigtree_pages",
					"entry" => $this->ID,
					"tag" => $tag->ID
				]);
			}
			
			// If this page is a trunk in a multi-site setup, wipe the cache
			foreach (Router::$SiteRoots as $site_path => $site_data) {
				if ($site_data["trunk"] == $this->ID) {
					unlink(SERVER_ROOT."cache/multi-site-cache.json");
				}
			}
			
			return true;
		}
		
		/*
		    Function: setTags
				Sets the page object's tags property with a new set of tag IDs.

			Parameters:
				tags - An array of tag IDs
		*/
		
		function setTags(?array $tags) {
			$this->Tags = [];
			
			if (is_array($tags)) {
				foreach ($tags as $tag_id) {
					$this->Tags[] = new Tag($tag_id);
				}
			}
		}
		
		/*
			Function: update
				Updates the page's properties and saves changes to the database.
				Creates a new page revision and erases old page revisions.

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
				resources - Array of page data
				publish_at - Publish time (or false for immediate publishing)
				expire_at - Expiration time (or false for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (0 for no max)
				tags - An array of tag IDs to apply to the page (optional)
		*/
		
		function update(?bool $trunk, ?int $parent, ?bool $in_nav, string $nav_title, string $title, string $route,
						string $meta_description, ?bool $seo_invisible, string $template, ?bool $external,
						?bool $new_window, array $resources, ?string $publish_at, ?string $expire_at, ?int $max_age,
						?array $tags = []) {
			// Save a page revision
			PageRevision::create($this);
			
			// Count the page revisions, if we have more than 10, delete any that are more than a month old
			$revision_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_page_revisions 
												WHERE page = ? AND saved = ''", $this->ID);
			
			if ($revision_count > 10) {
				SQL::query("DELETE FROM bigtree_page_revisions 
							WHERE page = ? AND updated_at < '".date("Y-m-d", strtotime("-1 month"))."' AND saved = '' 
							ORDER BY updated_at ASC LIMIT ".($revision_count - 10), $this->ID);
			}
			
			// Remove this page from the cache
			$this->uncache();
			
			// We have no idea how this affects the nav, just wipe it all.
			if ($this->NavigationTitle != $nav_title || $this->Route != $route || $this->InNav != $in_nav || $this->Parent != $parent) {
				Router::clearCache();
			}
			
			$this->ExpireAt = ($expire_at && $expire_at != "NULL") ? date("Y-m-d", strtotime($expire_at)) : null;
			$this->External = $external;
			$this->InNav = $in_nav;
			$this->MaxAge = $max_age;
			$this->MetaDescription = $meta_description;
			$this->NavigationTitle = $nav_title;
			$this->NewWindow = $new_window;
			$this->Parent = $parent;
			$this->PublishAt = ($publish_at && $publish_at != "NULL") ? date("Y-m-d", strtotime($publish_at)) : null;
			$this->Resources = $resources;
			$this->Route = $route ?: Link::urlify($nav_title);
			$this->SEOInvisible = $seo_invisible;
			$this->Template = $template;
			$this->Title = $title;
			$this->Trunk = $trunk;
	
			// Converts tag IDs into tag objects
			$this->setTags($tags);
			
			$this->save();
		}
		
		/*
			Function: updateChildrenPaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.
		*/
		
		function updateChildrenPaths(?int $page = null) {
			// Allow for recursion
			if ($page !== null) {
				$parent_path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $page);
			} else {
				$parent_path = $this->Path;
				$page = $this->ID;
			}
			
			$child_pages = SQL::fetchAll("SELECT id, route, path FROM bigtree_pages WHERE parent = ?", $page);
			
			foreach ($child_pages as $child) {
				$new_path = $parent_path."/".$child["route"];
				
				if ($child["path"] != $new_path) {
					// Remove any overlaps
					SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $new_path, $child["path"]);
					
					// Add a new redirect
					SQL::insert("bigtree_route_history", [
						"old_route" => $child["path"],
						"new_route" => $new_path
					]);
					
					// Update the primary path
					SQL::update("bigtree_pages", $child["id"], ["path" => $new_path]);
					
					// Update all this page's children as well
					$this->updateChildrenPaths($child["id"]);
				}
			}
		}
		
		/*
			Function: updateParent
				Changes the page's parent and adjusts child pages.
				Sets a special audit trail entry for "moved".

			Parameters:
				parent - The new parent page ID.
		*/
		
		function updateParent(int $parent) {
			$this->Parent = $parent;
			$this->save();
			
			// Track a movement
			AuditTrail::track("bigtree_pages", $this->ID, "moved");
		}
		
		/*
			Function: updatePosition
				Sets the position of the page without adjusting other columns.

			Parameters:
				position - The position to set.
		*/
		
		function updatePosition(int $position) {
			$this->Position = $position;
			SQL::update("bigtree_pages", $this->ID, ["position" => $position]);
		}
		
	}
	