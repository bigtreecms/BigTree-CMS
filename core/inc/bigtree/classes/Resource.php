<?php
	/*
		Class: BigTree\Resource
			Provides an interface for handling BigTree resources.
	*/

	namespace BigTree;

	class Resource extends BaseObject {

		public static $CreationLog = array();
		public static $Prefixes = array();
		public static $Table = "bigtree_resources";

		protected $ID;

		public $Crops;
		public $Date;
		public $File;
		public $Folder;
		public $Height;
		public $IsImage;
		public $ListThumbMargin;
		public $MD5;
		public $Name;
		public $Thumbs;
		public $Type;
		public $Width;

		/*
			Constructor:
				Builds a Resource object referencing an existing database entry.

			Parameters:
				resource - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($resource) {
			// Passing in just an ID
			if (!is_array($resource)) {
				$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $resource);
			}

			// Bad data set
			if (!is_array($resource)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$this->ID = $resource["id"];

				$this->Crops = array_filter((array) @json_decode($resource["crops"],true));
				$this->Date = $resource["date"];
				$this->File = Link::detokenize($resource["file"]);
				$this->Folder = $resource["folder"];
				$this->Height = $resource["height"];
				$this->IsImage = $resource["is_image"] ? true : false;
				$this->ListThumbMargin = $resource["list_thumb_margin"];
				$this->MD5 = $resource["md5"];
				$this->Name = $resource["name"];
				$this->Thumbs = array_filter((array) @json_decode($resource["thumbs"],true));
				foreach ($this->Thumbs as &$thumb) {
					$thumb = Link::detokenize($thumb);
				}
				$this->Type = $resource["type"];
				$this->Width = $resource["width"];
			}
		}

		/*
			Function: allocate
				Assigns resources from creation log and wipes creation log.

			Parameters:
				module - Module ID to assign to
				entry - Entry ID to assign to
		*/

		static function allocate($module,$entry) {
			// Wipe existing allocations
			SQL::delete("bigtree_resource_allocation",array(
				"module" => $module,
				"entry" => $entry
			));

			// Add new allocations
			foreach (static::$CreationLog as $resource) {
				SQL::insert("bigtree_resource_allocation",array(
					"module" => $module,
					"entry" => $entry,
					"resource" => $resource
				));
			}

			// Clear log
			static::$CreationLog = array();
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
			$id = SQL::insert("bigtree_resources",array(
				"file" => Link::tokenize($file),
				"md5" => $md5,
				"name" => Text::htmlEncode($name),
				"type" => $type,
				"folder" => $folder ? $folder : null,
				"is_image" => $is_image,
				"height" => intval($height),
				"width" => intval($width),
				"thumbs" => $thumbs
			));

			AuditTrail::track("bigtree_resources",$id,"created");

			return new Resource($id);
		}

		/*
			Function: delete
				Deletes the resource.
				If no resource allocations remain, the file is deleted as well.
		*/

		function delete() {
			// Delete resource record
			SQL::delete("bigtree_resources",$this->ID);
			AuditTrail::track("bigtree_resources",$this->ID,"deleted");

			// If this file isn't located in any other folders, delete it from the file system
			if (!SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_resources WHERE file = ?", Link::tokenize($this->File))) {
				$storage = new Storage;
				$storage->delete($this->File);

				// Delete any thumbnails as well
				foreach (array_filter((array)$this->Thumbs) as $thumb) {
					$storage->delete($thumb);
				}
			}
		}

		/*
			Function: getAllocationCount
				Returns the number of places this resource is in use.

			Returns:
				An integer.
		*/

		function getAllocationCount() {
			return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_resource_allocation WHERE resource = ?", $this->ID);
		}

		/*
			Function: getByFile
				Returns a resource with the given file name.

			Parameters:
				file - The file name.

			Returns:
				A Resource object or false if no matching resource was found.
		*/

		static function getByFile($file) {
			// Populate a list of resource prefixes if we don't already have it cached
			if (static::$Prefixes === false) {
				static::$Prefixes = array();
				$thumbnail_sizes = Setting::value("bigtree-file-manager-thumbnail-sizes");
				foreach ($thumbnail_sizes["value"] as $ts) {
					static::$Prefixes[] = $ts["prefix"];
				}
			}

			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ?", $file, Link::tokenize($file));
			
			// If we didn't find the resource, check all the prefixes
			if (!$resource) {
				foreach (static::$Prefixes as $prefix) {
					if (!$resource) {
						$prefixed_file = str_replace("files/resources/$prefix","files/resources/",$file);
						$resource = SQL::fetch("SELECT * FROM bigtree_resources
												WHERE file = ? OR file = ?", $file, Link::tokenize($prefixed_file));
					}
				}
				if (!$resource) {
					return false;
				}
			}

			return new Resource($resource);
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

			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE md5 = ? LIMIT 1", $md5);
			if (!$resource) {
				return false;
			}

			// If we already have this exact resource in this exact folder, just update its modification time
			if ($resource["folder"] == $new_folder) {
				SQL::update("bigtree_resources",$resource["id"],array("date" => "NOW()"));
			} else {
				// Make a copy of the resource
				unset($resource["id"]);
				$resource["date"] = "NOW()";
				$resource["folder"] = $new_folder ? $new_folder : null;

				SQL::insert("bigtree_resources",$resource);
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
			$query = SQL::escape($query);
			$permission_cache = array();

			// Get matching folders
			$folders = SQL::fetchAll("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");
			foreach ($folders as &$folder) {
				$folder_object = new ResourceFolder($folder);
				$folder["permission"] = $folder_object->UserAccessLevel;

				// We're going to cache the folder permissions so we don't have to fetch them a bunch of times if many files have the same folder.
				$permission_cache[$folder["id"]] = $folder["permission"];
			}

			// Get matching resources
			$resources = SQL::fetchAll("SELECT * FROM bigtree_resources WHERE name LIKE '%$query%' ORDER BY $sort");
			foreach ($resources as &$resource) {
				// If we've already got the permission cahced, use it.  Otherwise, fetch it and cache it.
				if ($permission_cache[$resource["folder"]]) {
					$resource["permission"] = $permission_cache[$resource["folder"]];
				} else {
					$folder = new ResourceFolder($resource["folder"]);
					$resource["permission"] = $folder->UserAccessLevel;
					$permission_cache[$resource["folder"]] = $resource["permission"];
				}
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Convert links
			foreach ($this->Crops as &$crop) {
				$crop = Link::tokenize($crop);
			}
			foreach ($this->Thumbs as &$thumb) {
				$thumb = Link::tokenize($thumb);
			}

			SQL::update("bigtree_resources",$this->ID,array(
				"folder" => $this->Folder,
				"file" => Link::tokenize($this->File),
				"md5" => $this->MD5,
				"date" => date("Y-m-d H:i:s",strtotime($this->Date)),
				"name" => $this->Name,
				"type" => $this->Type,
				"is_image" => $this->IsImage ? "on" : "",
				"height" => intval($this->Height),
				"width" => intval($this->Width),
				"crops" => $this->Crops,
				"thumbs" => $this->Thumbs,
				"list_thumb_margin" => intval($this->ListThumbMargin)
			));

			AuditTrail::track("bigtree_resources",$this->ID,"updated");
		}

	}
