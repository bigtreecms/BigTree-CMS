<?php
	/*
		Class: BigTree\Cache
			Provides an interface for the bigtree_caches table.
	*/

	namespace BigTree;
	
	use BigTreeCMS;

	class Page {

		/*
			Function: access
				Returns the access level for the provided user to a page.

			Parameters:
				page - The page id.
				user - The user entry or user id.

			Returns:
				"p" for publisher, "e" for editor, false for no access.

			See Also:
				<getPageAccessLevel>
		*/

		static function access($page,$user) {
			// See if this is a pending change, if so, grab the change's parent page and check permission levels for that instead.
			if (!is_numeric($page) && $page[0] == "p") {
				$pending_change = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($page,1));
				$changes = json_decode($pending_change["changes"],true);
				return static::getPageAccessLevelByUser($changes["parent"],$user);
			}

			// If we don't have a user entry, turn it into an entry
			if (!is_array($user)) {
				$user = static::getUser($user);
			}

			$level = $user["level"];
			$permissions = $user["permissions"];
		
			// See if the user is an administrator, if so we can skip permissions.
			if ($level > 0) {
				return "p";
			}

			// See if this page has an explicit permission set and return it if so.
			$explicit_permission = $permissions["page"][$page];
			if ($explicit_permission == "n") {
				return false;
			} elseif ($explicit_permission && $explicit_permission != "i") {
				return $explicit_permission;
			}

			// We're now assuming that this page should inherit permissions from farther up the tree, so let's grab the first parent.
			$page_parent = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page);

			// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
			$parent_permission = $permissions["page"][$page_parent];
			while ((!$parent_permission || $parent_permission == "i") && $page_parent) {
				$parent_id = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page_parent);
				$parent_permission = $permissions["page"][$parent_id];
			}

			// If no permissions are set on the page (we hit page 0 and still nothing) or permission is "n", return not allowed.
			if (!$parent_permission || $parent_permission == "i" || $parent_permission == "n") {
				return false;
			}

			// Return whatever we found.
			return $parent_permission;
		}

		/*
			Function: archivePage
				Archives a page.

			Parameters:
				page - Either a page id or page entry.

			Returns:
				true if successful. false if the logged in user doesn't have permission.

			See Also:
				<archivePageChildren>
		*/

		function archivePage($page) {
			$page = is_array($page) ? $page["id"] : $page;
			$access = $this->getPageAccessLevel($page);

			// Only users with publisher access that can also modify this page's children can archive it
			if ($access == "p" && $this->canModifyChildren(BigTreeCMS::getPage($page))) {
				// Archive the page and the page children
				static::$DB->update("bigtree_pages",$page,array("archived" => "on"));
				$this->archivePageChildren($page);

				// Track and growl
				static::growl("Pages","Archived Page");
				$this->track("bigtree_pages",$page,"archived");
				return true;
			}

			// No access
			return false;
		}
		
		
	}