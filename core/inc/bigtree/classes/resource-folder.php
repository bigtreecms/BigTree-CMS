<?php
	/*
		Class: BigTree\ResourceFolder
			Provides an interface for handling BigTree resource folders.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class ResourceFolder {

		/*
			Function: access
				Returns the access level of the current user for the folder.
				Can only be called within admin context.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				"p" if a user can create folders and upload files, "e" if the user can see/use files, "n" if a user can't access this folder.
		*/

		function access($folder) {
			global $admin;

			if (!$admin || get_class($admin) != "BigTreeAdmin") {
				return false;
			}

			// User is an admin or developer
			if ($admin->Level > 0) {
				return "p";
			}

			// We're going to save the folder entry in case we need its parent later.
			if (is_array($folder)) {
				$id = $folder["id"];
			} else {
				$id = $folder;
			}

			$p = $admin->Permissions["resources"][$id];
			// If p is already no, creator, or consumer we can just return it.
			if ($p && $p != "i") {
				return $p;
			} else {
				// If folder is 0, we're already at home and can't check a higher folder for permissions.
				if (!$folder) {
					return "e";
				}

				// If a folder entry wasn't passed in, we need it to find its parent.
				if (!is_array($folder)) {
					$folder = static::$DB->fetch("SELECT parent FROM bigtree_resource_folders WHERE id = ?", $id);
				}

				// If we couldn't find the folder anymore, just say they can consume.
				if (!$folder) {
					return "e";
				}

				// Return the parent's permissions
				return $this->getResourceFolderPermission($folder["parent"]);
			}
		}

		/*
			Function: breadcrumb
				Returns a breadcrumb of the given folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/

		static function breadcrumb($folder,$crumb = array()) {
			if (!is_array($folder)) {
				$folder = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resource_folders WHERE id = ?", $folder);
			}

			// Append the folder to the running breadcrumb
			if ($folder) {
				$crumb[] = array("id" => $folder["id"], "name" => $folder["name"]);
			}

			// If we have a parent, go higher up
			if ($folder["parent"]) {
				return static::breadcrumb($folder["parent"],$crumb);
			
			// Append home, reverse, return
			} else {
				$crumb[] = array("id" => 0, "name" => "Home");
				return array_reverse($crumb);
			}
		}

		/*
			Function: children
				Returns the child folders of a resource folder.

			Parameters:
				id - The id of the parent folder.

			Returns:
				An array of resource folder entries.
		*/

		static function children($id) {
			return BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_folders WHERE parent = ? ORDER BY name ASC", $id);
		}

		/*
			Function: contents
				Returns a list of resources and subfolders in a folder.

			Parameters:
				folder - The id of a folder or a folder entry.
				sort - The column to sort the folder's files on (default: date DESC).

			Returns:
				An array of two arrays - folders and resources.
		*/

		static function contents($folder, $sort = "date DESC") {
			if (is_array($folder)) {
				$folder = $folder["id"];
			}
			$null_query = $folder ? "" : "OR folder IS NULL";

			$folders = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_folders WHERE parent = ? ORDER BY name", $folder);
			$resources = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resources WHERE folder = ? $null_query ORDER BY $sort", $folder);

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: create
				Creates a resource folder.

			Parameters:
				parent - The parent folder.
				name - The name of the new folder.

			Returns:
				The new folder id or false if not allowed.
		*/

		function create($parent,$name) {
			$id = BigTreeCMS::$DB->insert("bigtree_resource_folders",array(
				"name" => BigTree::safeEncode($name),
				"parent" => $parent
			));

			BigTree\AuditTrail::track("bigtree_resource_folders",$id,"created");
			return $id;
		}

		/*
			Function: delete
				Deletes a resource folder and all of its sub folders and resources.

			Parameters:
				id - The id of the resource folder.
		*/

		function delete($id) {
			// Get everything inside the folder
			$items = static::contents($id);

			// Delete all subfolders
			foreach ($items["folders"] as $folder) {
				static::delete($folder["id"]);
			}

			// Delete all files
			foreach ($items["resources"] as $resource) {
				BigTree\Resource::delete($resource["id"]);
			}

			// Delete the folder
			BigTreeCMS::$DB->delete("bigtree_resource_folders",$id);
			BigTree\AuditTrail::track("bigtree_resource_folders",$id,"deleted");
		}

		/*
			Function: get
				Returns a resource folder.

			Parameters:
				id - The id of the folder.

			Returns:
				A resource folder entry.
		*/

		static function get($id) {
			return BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resource_folders WHERE id = ?", $id);
		}

		/*
			Function: info
				Returns the number of items inside a folder and it's subfolders and the number of allocations of the contained resources.

			Parameters:
				folder - The id of the folder.

			Returns:
				A keyed array of "resources", "folders", and "allocations" for the number of resources, sub folders, and allocations.
		*/

		static function info($folder) {
			$allocations = $folders = $resources = 0;
			$items = static::contents($folder);

			// Loop through subfolders
			foreach ($items["folders"] as $folder) {
				$folders++;
				$subs = static::info($folder["id"]);
				$allocations += $subs["allocations"];
				$folders += $subs["folders"];
				$resources += $subs["resources"];
			}

			foreach ($items["resources"] as $resource) {
				$resources++;
				$allocations += count(BigTree\Resource::allocation($resource["id"]));
			}

			return array("allocations" => $allocations,"folders" => $folders,"resources" => $resources);
		}
	}
