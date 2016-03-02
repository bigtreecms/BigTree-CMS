<?php
	/*
		Class: BigTree\Resource
			Provides an interface for handling BigTree resources.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class Resource {

		public static $CreationLog = array();
		public static $Prefixes = array();

		/*
			Function: allocate
				Assigns resources from creation log and wipes creation log.

			Parameters:
				module - Module ID to assign to
				entry - Entry ID to assign to
		*/

		static function allocate($module,$entry) {
			// Wipe existing allocations
			BigTreeCMS::$DB->delete("bigtree_resource_allocation",array(
				"module" => $module,
				"entry" => $entry
			));

			// Add new allocations
			foreach (static::$CreationLog as $resource) {
				BigTreeCMS::$DB->insert("bigtree_resource_allocation",array(
					"module" => $module,
					"entry" => $entry,
					"resource" => $resource
				));
			}

			static::$CreationLog = array();
		}

		/*
			Function: allocation
				Returns the places a resource is used.

			Parameters:
				id - The id of the resource.

			Returns:
				An array of entries from the bigtree_resource_allocation table.
		*/

		static function allocation($id) {
			return BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC", $id);
		}

		/*
			Function: create
				Creates a resource.

			Parameters:
				folder - The folder to place it in.
				file - The file path.
				md5 - The MD5 hash of the file.
				name - The file name.
				type - The file type.
				is_image - Whether the resource is an image.
				height - The image height (if it's an image).
				width - The image width (if it's an image).
				thumbs - An array of thumbnails (if it's an image).

			Returns:
				The new resource id.
		*/

		static function create($folder,$file,$md5,$name,$type,$is_image = "",$height = 0,$width = 0,$thumbs = array()) {
			$id = BigTreeCMS::$DB->insert("bigtree_resources",array(
				"file" => BigTree\Link::tokenize($file),
				"md5" => $md5,
				"name" => BigTree::safeEncode($name),
				"type" => $type,
				"folder" => $folder ? $folder : null,
				"is_image" => $is_image,
				"height" => intval($height),
				"width" => intval($width),
				"thumbs" => $thumbs
			));

			BigTree\AuditTrail::track("bigtree_resources",$id,"created");
			return $id;
		}

		/*
			Function: delete
				Deletes a resource.

			Parameters:
				id - The id of the resource.
		*/

		static function delete($id) {
			$resource = static::get($id);
			if (!$resource) {
				return false;
			}

			// Delete resource record
			BigTreeCMS::$DB->delete("bigtree_resources",$id);
			BigTree\AuditTrail::track("bigtree_resources",$id,"deleted");

			// If this file isn't located in any other folders, delete it from the file system
			if (!BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_resources WHERE file = ?", $resource["file"])) {
				$storage = new BigTreeStorage;
				$storage->delete($resource["file"]);

				// Delete any thumbnails as well
				foreach (array_filter((array)$resource["thumbs"]) as $thumb) {
					$storage->delete($thumb);
				}
			}
		}

		/*
			Function: file
				Returns a resource with the given file name.

			Parameters:
				file - The file name.

			Returns:
				An entry from bigtree_resources with file and thumbs decoded.
		*/

		static function file($file) {
			// Populate a list of resource prefixes if we don't already have it cached
			if (static::$Prefixes === false) {
				static::$Prefixes = array();
				$thumbnail_sizes = BigTree\Setting::value("bigtree-file-manager-thumbnail-sizes");
				foreach ($thumbnail_sizes["value"] as $ts) {
					static::$Prefixes[] = $ts["prefix"];
				}
			}

			$last_prefix = false;
			$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ?", 
												$file, BigTree\Link::tokenize($file));
			
			// If we didn't find the resource, check all the prefixes
			if (!$resource) {
				foreach (static::$Prefixes as $prefix) {
					if (!$resource) {
						$prefixed_file = str_replace("files/resources/$prefix","files/resources/",$file);
						$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources
															WHERE file = ? OR file = ?", $file, BigTree\Link::tokenize($prefixed_file));
						$last_prefix = $prefix;
					}
				}
				if (!$resource) {
					return false;
				}
			}

			// Decode some things
			$resource["prefix"] = $last_prefix;
			$resource["file"] = BigTree\Link::detokenize($resource["file"]);
			$resource["thumbs"] = json_decode($resource["thumbs"],true);
			foreach ($resource["thumbs"] as &$thumb) {
				$thumb = BigTree\Link::detokenize($thumb);
			}

			return $resource;
		}

		/*
			Function: get
				Returns a resource.

			Parameters:
				id - The id of the resource.

			Returns:
				A resource entry with thumbnails decoded.
		*/

		static function get($id) {
			$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);
			$resource["thumbs"] = json_decode($resource["thumbs"],true);
			return $resource;
		}

		/*
			Function: md5Check
				Checks if the given file is a MD5 match for any existing resources.
				If a match is found, the resource is "copied" into the given folder (unless it already exists in that folder).

			Parameters:
				file - Uploaded file to run MD5 hash on
				new_folder - Folder the given file is being uploaded into

			Returns:
				true if a match was found. If the file was already in the given folder, the date is simply updated.
		*/

		static function md5Check($file,$new_folder) {
			$md5 = md5_file($file);

			$resource = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_resources WHERE md5 = ? LIMIT 1", $md5);
			if (!$resource) {
				return false;
			}

			// If we already have this exact resource in this exact folder, just update its modification time
			if ($resource["folder"] == $new_folder) {
				BigTreeCMS::$DB->update("bigtree_resources",$resource["id"],array("date" => "NOW()"));
			} else {
				// Make a copy of the resource
				unset($resource["id"]);
				$resource["date"] = "NOW()";
				$resource["folder"] = $new_folder ? $new_folder : null;

				BigTreeCMS::$DB->insert("bigtree_resources",$resource);
			}

			return true;
		}

		/*
			Function: search
				Returns a list of folders and files that match the given query string.

			Parameters:
				query - A string of text to search folders' and files' names to.
				sort - The column to sort the files on (default: date DESC).

			Returns:
				An array of two arrays - folders and files - with permission levels.
		*/

		static function search($query, $sort = "date DESC") {
			$query = BigTreeCMS::$DB->escape($query);
			$folders = $resources = $permission_cache = array();

			// Get matching folders
			$folders = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");
			foreach ($folders as &$folder) {
				$folder["permission"] = BigTree\ResourceFolder::permission($folder);

				// We're going to cache the folder permissions so we don't have to fetch them a bunch of times if many files have the same folder.
				$permission_cache[$folder["id"]] = $folder["permission"];
			}

			// Get matching resources
			$resources = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resources WHERE name LIKE '%$query%' ORDER BY $sort");
			foreach ($resources as &$resource) {
				// If we've already got the permission cahced, use it.  Otherwise, fetch it and cache it.
				if ($permission_cache[$resource["folder"]]) {
					$resource["permission"] = $permission_cache[$resource["folder"]];
				} else {
					$resource["permission"] = BigTree\ResourceFolder::permission($resource["folder"]);
					$permission_cache[$resource["folder"]] = $resource["permission"];
				}
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: update
				Updates a resource.

			Parameters:
				id - The id of the resource.
				attributes - A key/value array of fields to update.
		*/

		static function update($id,$attributes) {
			BigTreeCMS::$DB->update("bigtree_resources",$id,$attributes);
			BigTree\AuditTrail::track("bigtree_resources",$id,"updated");
		}
	}
