<?php
	/*
		Class: BigTree\ResourceFolder
			Provides an interface for handling BigTree resource folders.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class ResourceFolder {

		static $Table = "bigtree_resource_folders";

		protected $ID;

		public $Name;
		public $Parent;

		/*
			Constructor:
				Builds a ResourceFolder object referencing an existing database entry.

			Parameters:
				folder - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($folder) {
			// Passing in just an ID
			if (!is_array($folder)) {
				$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resource_folders WHERE id = ?", $folder);
			}

			// Bad data set
			if (!is_array($folder)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $folder["id"];

				$this->Name = $folder["name"];
				$this->Parent = $folder["parent"];
			}
		}

		// $this->Breadcrumb
		protected function _getBreadcrumb($folder = false,$crumb = array()) {
			// First call won't have folder
			if (!$folder) {
				$folder = $this;
			}

			// Add crumb part
			$crumb[] = array("id" => $folder->ID, "name" => $folder->Name);

			// If we have a parent, go higher up
			if ($folder->Parent) {
				return $this->_getBreadcrumb(new ResourceFolder($this->Parent),$crumb);
			
			// Append home, reverse, return
			} else {
				$crumb[] = array("id" => 0, "name" => "Home");
				return array_reverse($crumb);
			}
		}

		// $this->Contents
		protected function _getContents($sort = "date DESC") {
			$null_query = $this->ID ? "" : "OR folder IS NULL";

			$folders = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_folders WHERE parent = ? ORDER BY name", $this->ID);
			$resources = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resources WHERE folder = ? $null_query ORDER BY $sort", $this->ID);

			return array("folders" => $folders, "resources" => $resources);
		}

		// $this->Statistics
		protected function _getStatistics() {
			$allocations = $folders = $resources = 0;
			$items = $this->Contents;

			// Loop through subfolders
			foreach ($items["folders"] as $folder) {
				$folders++;

				$sub_folder = new ResourceFolder($folder);
				$sub_folder_stats = $sub_folder->Statistics;

				$allocations += $sub_folder_stats["allocations"];
				$folders += $sub_folder_stats["folders"];
				$resources += $sub_folder_stats["resources"];
			}

			foreach ($items["resources"] as $resource) {
				$resources++;

				$resource = new Resource($resource);
				$allocations += $resource->AllocationCount;
			}

			return array("allocations" => $allocations,"folders" => $folders,"resources" => $resources);
		}

		// $this->UserAccessLevel
		protected function _getUserAccessLevel($recursion = false) {
			// Not much, but skip it since it's not needed on recursion
			if ($recursion == false) {
				global $admin;
		
				// Make sure a user is logged in
				if (get_class($admin) != "BigTreeAdmin" || $admin->ID) {
					trigger_error("Property UserAccessLevel not available outside logged-in user context.");
					return false;
				}
		
				// User is an admin or developer
				if ($admin->Level > 0) {
					return "p";
				}

				$id = $this->ID;
			} else {
				$id = $recursion;
			}

			$permission = $admin->Permissions["resources"][$id];
			// If permission is already no, creator, or consumer we can just return it.
			if ($permission && $permission != "i") {
				return $permission;
			} else {
				// If folder is 0, we're already at home and can't check a higher folder for permissions.
				if (!$id) {
					return "e";
				}

				// Find parent folder
				$parent_folder = ($this->ID == $id) ? $this->Parent : BigTreeCMS::$DB->fetchSingle("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $id);

				// Return the parent's permissions
				return $this->_getUserAccessLevel($parent_folder);
			}
		}

		/*
			Function: create
				Creates a resource folder.

			Parameters:
				parent - The parent folder.
				name - The name of the new folder.

			Returns:
				A ResourceFolder object.
		*/

		static function create($parent,$name) {
			$id = BigTreeCMS::$DB->insert("bigtree_resource_folders",array(
				"name" => BigTree::safeEncode($name),
				"parent" => $parent
			));

			AuditTrail::track("bigtree_resource_folders",$id,"created");

			return new ResourceFolder($id);
		}

		/*
			Function: delete
				Deletes the resource folder and all of its sub folders and resources.
		*/

		function delete() {
			// Get everything inside the folder
			$items = $this->Contents;

			// Delete all subfolders
			foreach ($items["folders"] as $folder) {
				$folder = new ResourceFolder($folder);
				$folder->delete();
			}

			// Delete all files
			foreach ($items["resources"] as $resource) {
				$resource = new Resource($resource);
				$resource->delete();
			}

			// Delete the folder
			BigTreeCMS::$DB->delete("bigtree_resource_folders",$this->ID);
			AuditTrail::track("bigtree_resource_folders",$this->ID,"deleted");
		}

		/*
			Function: root
				Returns a ResourceFolder object for the root folder.

			Returns:
				A ResourceFolder object.
		*/

		static function root() {
			return new ResourceFolder(array("id" => "0", "parent" => "-1", "name" => "Home"));
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			BigTreeCMS::$DB->update("bigtree_resource_folders",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"parent" => intval($this->Parent)
			));

			AuditTrail::track("bigtree_resource_folders",$this->ID,"updated");
		}

	}
