<?php
	/*
		Class: BigTree\Page
			Provides an interface for BigTree pages.
	*/

	namespace BigTree;
	
	use BigTreeCMS;

	class Page {

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
				$this->ID = $page["id"];
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
	}