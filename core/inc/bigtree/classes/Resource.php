<?php
	/*
		Class: BigTree\Resource
			Provides an interface for handling BigTree resources.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read int $AllocationCount
	 * @property-read string $UserAccessLevel
	 */
	class Resource extends SQLObject
	{
		
		protected $ID;
		
		public $Crops;
		public $Date;
		public $File;
		public $FileSize;
		public $Folder;
		public $Height;
		public $IsImage;
		public $IsVideo;
		public $Location;
		public $Metadata;
		public $MimeType;
		public $Name;
		public $Thumbs;
		public $Type;
		public $Width;
		public $VideoData;
		
		public static $CreationLog = [];
		public static $Prefixes = [];
		public static $Table = "bigtree_resources";
		
		/*
			Constructor:
				Builds a Resource object referencing an existing database entry.

			Parameters:
				resource - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($resource = null)
		{
			if ($resource !== null) {
				// Passing in just an ID
				if (!is_array($resource)) {
					$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $resource);
				}
				
				// Bad data set
				if (!is_array($resource)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $resource["id"];
					
					$this->Crops = Link::detokenize(array_filter((array) @json_decode($resource["crops"], true)));
					$this->Date = $resource["date"];
					$this->File = Link::detokenize($resource["file"]);
					$this->FileSize = $resource["size"];
					$this->Folder = $resource["folder"];
					$this->Height = $resource["height"];
					$this->IsImage = $resource["is_image"] ? true : false;
					$this->IsVideo = $resource["is_video"] ? true : false;
					$this->Location = $resource["location"];
					$this->Metadata = Link::detokenize(array_filter((array) @json_decode($resource["metadata"], true)));
					$this->MimeType = $resource["mimetype"];
					$this->Name = $resource["name"];
					$this->Thumbs = Link::detokenize(array_filter((array) @json_decode($resource["thumbs"], true)));
					$this->Type = $resource["type"];
					$this->Width = $resource["width"];
					$this->VideoData = Link::detokenize(array_filter((array) @json_decode($resource["video_data"], true)));
				}
			}
		}
		
		/*
			Function: allocate
				Assigns resources from creation log and wipes creation log.

			Parameters:
				table - Table in which the entry resides
				entry - Entry ID to assign to
		*/
		
		public static function allocate(string $table, string $entry): void
		{
			// Wipe existing allocations
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
			
			// Add new allocations
			foreach (static::$CreationLog as $resource) {
				SQL::insert("bigtree_resource_allocation", [
					"table" => $table,
					"entry" => $entry,
					"resource" => $resource,
					"updated_at" => "NOW()"
				]);
			}
			
			// Clear log
			static::$CreationLog = [];
		}
		
		/*
			Function: create
				Creates a resource.

			Parameters:
				folder - The folder to place it in.
				file - The file path or a video URL.
				name - The file name.
				type - "file", "image", or "video"
				crops - An array of crop prefixes
				thumbs - An array of thumb prefixes
				video_data - An array of video data
				metadata - An array of metadata

			Returns:
				A Resource object.
		*/
		
		public static function create(?int $folder, ?string $file, ?string $name, string $type, array $crops = [],
									  array $thumbs = [], array $video_data = [], array $metadata = []): Resource
		{
			$width = null;
			$height = null;
			
			if ($type != "video") {
				$storage = new Storage;
				$location = $storage->Cloud ? "cloud" : "local";
				$file_extension = pathinfo($file, PATHINFO_EXTENSION);
				$authenticated_user_id = Auth::user()->ID;
				
				// Local storage will let us lookup file size
				if ($location == "local") {
					$file_path = str_replace(STATIC_ROOT, SITE_ROOT, Link::detokenize($file));
				} else {
					$file_path = SITE_ROOT."files/temporary/$authenticated_user_id/".uniqid(true).".".$file_extension;
					FileSystem::copyFile($file, $file_path);
				}
				
				$file_size = filesize($file_path);
				$mimetype = function_exists("mime_content_type") ? mime_content_type($file_path) : "";
				
				if ($type == "image") {
					list($width, $height) = getimagesize($file_path);
				}
				
				if ($location != "local") {
					unlink($file_path);
				}
			} else {
				$location = $video_data["service"];
				$file_extension = "video";
				$name = $video_data["title"];
				$file = $video_data["url"];
				$file_size = null;
				$mimetype = null;
			}
			
			$data = [
				"folder" => $folder ?: null,
				"file" => Link::tokenize($file),
				"name" => Text::htmlEncode($name),
				"type" => $file_extension,
				"mimetype" => $mimetype,
				"is_image" => ($type == "image") ? "on" : "",
				"is_video" => ($type == "video") ? "on" : "",
				"size" => $file_size,
				"width" => $width,
				"height" => $height,
				"date" => date("Y-m-d H:i:s"),
				"crops" => Link::tokenize($crops),
				"thumbs" => Link::tokenize($thumbs),
				"location" => $location,
				"video_data" => $video_data,
				"metadata" => Link::tokenize($metadata)
			];
			
			$id = SQL::insert("bigtree_resources", $data);
			AuditTrail::track("bigtree_resources", $id, "created");
			
			return new Resource($id);
		}
		
		/*
			Function: deallocate
				Removes resource allocation from a deleted entry.

			Parameters:
				table - The table of the entry
				entry - The ID of the entry
		*/
		
		public static function deallocate(string $table, $entry): void {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
		}
		
		/*
			Function: delete
				Deletes the resource.
				If no resource allocations remain, the file is deleted as well.
		*/
		
		public function delete(): ?bool
		{
			// Delete resource record
			SQL::delete("bigtree_resources", $this->ID);
			AuditTrail::track("bigtree_resources", $this->ID, "deleted");
			
			// If this file isn't located in any other folders, delete it from the file system
			if (!SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_resources WHERE file = ?", Link::tokenize($this->File))) {
				$storage = new Storage;
				$storage->delete($this->File);
				
				// Delete any thumbnails and crops as well
				foreach (array_filter((array) $this->Thumbs) as $prefix => $dimensions) {
					$storage->delete(FileSystem::getPrefixedFile($this->File, $prefix));
				}
				
				foreach (array_filter((array) $this->Crops) as $prefix => $dimensions) {
					$storage->delete(FileSystem::getPrefixedFile($this->File, $prefix));
				}
				
				// Delete the list preview
				$storage->delete(FileSystem::getPrefixedFile($this->File, "list-preview/"));
			}
			
			return true;
		}
		
		/*
			Function: getAllocationCount
				Returns the number of places this resource is in use.

			Returns:
				An integer.
		*/
		
		public function getAllocationCount(): int
		{
			return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_resource_allocation WHERE resource = ?", $this->ID);
		}
		
		/*
			Function: getByFile
				Returns a resource with the given file name.

			Parameters:
				file - The file name.

			Returns:
				A Resource object or null if no matching resource was found.
		*/
		
		public static function getByFile(string $file): ?Resource
		{
			// Populate a list of resource prefixes if we don't already have it cached
			if (static::$Prefixes === false) {
				static::$Prefixes = [];
				$thumbnail_sizes = Setting::value("bigtree-file-manager-thumbnail-sizes");
				
				foreach ($thumbnail_sizes["value"] as $ts) {
					static::$Prefixes[] = $ts["prefix"];
				}
			}
			
			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ?", $file, Link::tokenize($file));
			$last_prefix = "";
			
			if (!$resource) {
				// If we didn't find the resource, check all the prefixes
				$tokenized_file = Link::tokenize($file);
				$single_domain_tokenized_file = Link::stripMultipleRootTokens($tokenized_file);
				
				$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ? OR file = ?",
									   $file, $tokenized_file, $single_domain_tokenized_file);
				
				if (!$resource) {
					$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ? OR file = ?",
										   $file, str_replace("{wwwroot}", "{staticroot}", $tokenized_file),
										   $single_domain_tokenized_file);
				}
			}
			
			if (empty($resource)) {
				foreach (static::$Prefixes as $prefix) {
					if (empty($resource)) {
						$prefixed_file = str_replace("files/resources/$prefix", "files/resources/", $file);
						$tokenized_file = Link::tokenize($prefixed_file);
						$single_domain_tokenized_file = Link::stripMultipleRootTokens($tokenized_file);
						
						$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE file = ? OR file = ? OR file = ?",
											   $file, $tokenized_file, $single_domain_tokenized_file);
						
						$last_prefix = $prefix;
					}
				}
				
				if (!$resource) {
					return null;
				}
			}
			
			$resource = new Resource($resource);
			$resource->Prefix = $last_prefix;
			
			return $resource;
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level of the current user for this file.

			Returns:
				"p" if a user can modify this file, "e" if the user can use this file, "n" if a user can't access this file.
		*/
		
		public function getUserAccessLevel(): ?string
		{
			$folder = new ResourceFolder($this->Folder);
			
			return $folder->UserAccessLevel;
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
		
		public static function search(string $query, string $sort = "date DESC"): array
		{
			$query = SQL::escape($query);
			$permission_cache = [];
			
			// Get matching folders
			$folders = SQL::fetchAll("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");
			
			foreach ($folders as &$folder) {
				$folder_object = new ResourceFolder($folder);
				$folder["permission"] = $folder_object->UserAccessLevel;
				
				// We're going to cache the folder permissions so we don't have to fetch them a bunch of times if many files have the same folder.
				$permission_cache[$folder["id"]] = $folder["permission"];
			}
			
			// Get matching resources
			$resources = SQL::fetchAll("SELECT * FROM bigtree_resources
										WHERE name LIKE '%$query%' OR metadata LIKE '%$query%'
										ORDER BY $sort");
			$matching_resources = [];
			
			foreach ($resources as $resource) {
				// If we've already got the permission cached, use it. Otherwise, fetch it and cache it.
				if ($permission_cache[$resource["folder"]]) {
					$resource["permission"] = $permission_cache[$resource["folder"]];
				} else {
					$folder = new ResourceFolder($resource["folder"]);
					$resource["permission"] = $folder->UserAccessLevel;
					$permission_cache[$resource["folder"]] = $resource["permission"];
				}
				
				$matching_resources[] = $resource;
			}
			
			return ["folders" => $folders, "resources" => $matching_resources];
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create($this->Folder, $this->File, $this->Name, $this->Type, $this->Crops, $this->Thumbs,
									  $this->VideoData, $this->Metadata);
				$this->inherit($new);
			} else {
				SQL::update("bigtree_resources", $this->ID, [
					"folder" => intval($this->Folder) ?: null,
					"file" => Link::tokenize($this->File),
					"date" => date("Y-m-d H:i:s", strtotime($this->Date)),
					"name" => Text::htmlEncode($this->Name),
					"type" => $this->Type,
					"mimetype" => $this->MimeType,
					"metadata" => Link::encode($this->Metadata),
					"is_image" => $this->IsImage ? "on" : "",
					"is_video" => $this->IsVideo ? "on" : "",
					"height" => intval($this->Height),
					"width" => intval($this->Width),
					"size" => intval($this->FileSize),
					"crops" => Link::tokenize($this->Crops),
					"thumbs" => Link::tokenize($this->Thumbs),
					"video_data" => Link::tokenize($this->VideoData)
				]);
				
				AuditTrail::track("bigtree_resources", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: updatePendingAllocation
				Moves resource allocation from a pending ID to a published ID.
		
			Parameters:
				pending_id - The ID of the pending change
				table - The table containing the published entry
				entry - The ID of the published entry
		
		*/
		
		public static function updatePendingAllocation(int $pending_id, string $table, int $entry): void
		{
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
			SQL::update("bigtree_resource_allocation", ["table" => $table, "entry" => "p".$pending_id], ["entry" => $entry]);
		}
		
	}
