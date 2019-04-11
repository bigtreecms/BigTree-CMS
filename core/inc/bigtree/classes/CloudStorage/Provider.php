<?php
	/*
	    Class: BigTree\CloudStorage\Provider
			Provides a base implementation of other cloud storage systems.
	*/
	
	namespace BigTree\CloudStorage;
	
	use BigTree\OAuth;
	use BigTree\SQL;
	
	class Provider extends OAuth
	{
		
		public $Active = false;
		public $Errors = [];
		public $Settings = [];
		
		protected $MimeExtensions = [
			"jpg" => "image/jpeg", "jpeg" => "image/jpeg", "gif" => "image/gif",
			"png" => "image/png", "ico" => "image/x-icon", "pdf" => "application/pdf",
			"tif" => "image/tiff", "tiff" => "image/tiff", "svg" => "image/svg+xml",
			"svgz" => "image/svg+xml", "swf" => "application/x-shockwave-flash",
			"zip" => "application/zip", "gz" => "application/x-gzip",
			"tar" => "application/x-tar", "bz" => "application/x-bzip",
			"bz2" => "application/x-bzip2",  "rar" => "application/x-rar-compressed",
			"exe" => "application/x-msdownload", "msi" => "application/x-msdownload",
			"cab" => "application/vnd.ms-cab-compressed", "txt" => "text/plain",
			"asc" => "text/plain", "htm" => "text/html", "html" => "text/html",
			"css" => "text/css", "js" => "text/javascript",
			"xml" => "text/xml", "xsl" => "application/xsl+xml",
			"ogg" => "application/ogg", "mp3" => "audio/mpeg", "wav" => "audio/x-wav",
			"avi" => "video/x-msvideo", "mpg" => "video/mpeg", "mpeg" => "video/mpeg",
			"mov" => "video/quicktime", "flv" => "video/x-flv", "php" => "text/x-php"
		];
		
		/*
			Constructor:
				Retrieves the current service settings.
		*/
		
		function __construct()
		{
			parent::__construct("bigtree-internal-cloud-storage", "Cloud Storage", "org.bigtreecms.cloudstorage.api");
		}
		
		/*
			Function: copyFile
				Copies a file from one container/location to another container/location.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				source_container - The container the file is stored in.
				source_pointer - The full file path inside the container.
				destination_container - The container to copy the source file to.
				destination_pointer - The full file path to store the copied file
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)

			Returns:
				The URL of the file if successful.
		*/
		
		function copyFile(string $source_container, string $source_pointer, string $destination_container,
						  string $destination_pointer, bool $public = false): ?string
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: createContainer
				Creates a new container/bucket.
				Rackspace Cloud Files: If public is set to true the container be CDN-enabled and all of its contents will be publicly readable.
				Amazon: If public is set to true the bucket will have a policy making everything inside the bucket public.
				Google: If public is set to true the bucket will set the default access control on objects to public but they can be later changed.

			Parameters:
				name - Container name (keep in mind this must be unique among all other containers)
				public - true for public, defaults to false

			Returns:
				true if successful.
		*/
		
		function createContainer(string $name, bool $public = false): ?bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: createFile
				Creates a new file in the given container.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				contents - What to write to the file.
				container - Container name.
				pointer - The full file path inside the container.
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)
				type - MIME type (defaults to "text/plain")

			Returns:
				The URL of the file if successful.
		*/
		
		function createFile(string $contents, string $container, string $pointer, bool $public = false,
							string $type = "text/plain"): ?string
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: createFolder
				Creates a new folder in the given container.

			Parameters:
				container - Container name.
				pointer - The full folder path inside the container.

			Returns:
				The URL of the folder if successful.
		*/
		
		function createFolder(string $container, string $pointer): ?string
		{
			return $this->createFile("", $container, rtrim($pointer, "/")."/");
		}
		
		/*
			Function: deleteContainer
				Deletes a container/bucket.
				Containers must be empty to be deleted.

			Parameters:
				container - Container to delete.

			Returns:
				true if successful.
		*/
		
		function deleteContainer(string $container): ?bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: deleteFile
				Deletes a file from the given container.

			Parameters:
				container - The container the file is stored in.
				pointer - The full file path inside the container.

			Returns:
				true if successful
		*/
		
		function deleteFile(string $container, string $pointer): ?bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: getAuthenticatedFileURL
				Returns a URL that is valid for a limited amount of time to a private file.

			Parameters:
				container - The container the file is in.
				pointer - The full file path inside the container.
				expires - The number of seconds before this URL will expire.

			Returns:
				A URL for accessing the file if successful.
		*/
		
		function getAuthenticatedFileURL(string $container, string $pointer, int $expires): ?string
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: getContainer
				Lists the contents of a container/bucket.

			Parameters:
				container - The name of the container.
				simple - Simple mode (returns only a flat array with name/path/size, defaults to false)

			Returns:
				An array of the contents of the container.
		*/
		
		function getContainer(string $container, bool $simple = false): ?array
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
		    Function: getContainerTree
				Provides a tree structure of the contents of a container.

			Parameters:
				flat - An array of entries from a container

			Returns:
				A nested array emulating a branching folder tree.
		*/
		
		function getContainerTree(array $flat): array
		{
			$tree = ["folders" => [], "files" => []];
			
			foreach ($flat as $raw_item) {
				$keys = explode("/", $raw_item["name"]);
				// We're going to use by reference vars to figure out which folder to place this in
				$count = count($keys);
				
				if ($count > 1) {
					$folder = &$tree;
					
					for ($i = 0; $i < $count; $i++) {
						// Last part of the key and also has a . so we know it's actually a file
						if ($i == ($count - 1) && strpos($keys[$i], ".") !== false) {
							$raw_item["name"] = $keys[$i];
							$folder["files"][] = $raw_item;
						} else {
							if ($keys[$i]) {
								
								if (!isset($folder["folders"][$keys[$i]])) {
									$folder["folders"][$keys[$i]] = ["folders" => [], "files" => []];
								}
								
								$folder = &$folder["folders"][$keys[$i]];
							}
						}
					}
				} else {
					$tree["files"][] = $raw_item;
				}
			}
			
			return $tree;
		}
		
		/*
		    Function: getContentType
				Gets the MIME content type of a file.

			Parameters:
				file - The file path

			Returns:
				MIME Type
		*/
		
		function getContentType(string $file): string
		{
			$mime_types = [
				"jpg" => "image/jpeg", "jpeg" => "image/jpeg", "gif" => "image/gif",
				"png" => "image/png", "ico" => "image/x-icon", "pdf" => "application/pdf",
				"tif" => "image/tiff", "tiff" => "image/tiff", "svg" => "image/svg+xml",
				"svgz" => "image/svg+xml", "swf" => "application/x-shockwave-flash",
				"zip" => "application/zip", "gz" => "application/x-gzip",
				"tar" => "application/x-tar", "bz" => "application/x-bzip",
				"bz2" => "application/x-bzip2", "rar" => "application/x-rar-compressed",
				"exe" => "application/x-msdownload", "msi" => "application/x-msdownload",
				"cab" => "application/vnd.ms-cab-compressed", "txt" => "text/plain",
				"asc" => "text/plain", "htm" => "text/html", "html" => "text/html",
				"css" => "text/css", "js" => "text/javascript",
				"xml" => "text/xml", "xsl" => "application/xsl+xml",
				"ogg" => "application/ogg", "mp3" => "audio/mpeg", "wav" => "audio/x-wav",
				"avi" => "video/x-msvideo", "mpg" => "video/mpeg", "mpeg" => "video/mpeg",
				"mov" => "video/quicktime", "flv" => "video/x-flv", "php" => "text/x-php"
			];
			
			$path_info = pathinfo($file);
			$extension = strtolower($path_info["extension"]);
			
			return isset($mime_types[$extension]) ? $mime_types[$extension] : "application/octet-stream";
		}
		
		/*
			Function: getFile
				Returns a file from the given container.

			Parameters:
				container - The container the file is stored in.
				pointer - The full file path inside the container.

			Returns:
				A binary stream of data or false if the file is not found or not allowed.
		*/
		
		function getFile(string $container, string $pointer): ?string
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: getFolder
				Returns the folder "contents" from a container.

			Parameters:
				container - Either a string of the name of the container the folder resides in OR the response from a previous getContainer call.
				folder - The full folder path inside the container.

			Returns:
				A keyed array of files and folders inside the folder or false if the folder was not found.
		*/
		
		function getFolder(string $container, string $folder): ?array
		{
			if (!is_array($container)) {
				$container = $this->getContainer($container);
				
				if (is_null($container)) {
					return null;
				}
			}
			
			$folder_parts = explode("/", trim($folder, "/"));
			$tree = $container["tree"];
			
			foreach ($folder_parts as $part) {
				$tree = isset($tree["folders"][$part]) ? $tree["folders"][$part] : false;
			}
			
			return $tree;
		}
		
		/*
			Function: invalidateCache
				Invalidates the CloudFront cache for a given pointer.

			Parameters:
				pointer - The pointer to invalidate

			Returns:
				true if successful
		*/
		
		public function invalidateCache($pointer): bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return false;
		}
		
		/*
			Function: listContainers
				Lists containers/buckets that are available in this cloud account.

			Returns:
				An array of container names.
		*/
		
		function listContainers(): ?array
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: makeFilePublic
				Makes a file readable to the public.
				Rackspace Cloud Files does not support this method.

			Parameters:
				container - The container/bucket the file is in
				pointer - The pointer to the file.

			Returns:
				The public URL if successful, otherwise null
		*/
		
		function makeFilePublic(string $container, string $pointer): bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return false;
		}
		
		/*
			Function: resetCache
				Clears the bigtree_caches table of container data and resets it with new data.

			Parameters:
				data - An array of file data from a container
		*/
		
		function resetCache(array $data): void
		{
			SQL::delete("bigtree_caches", ["identifier" => "org.bigtreecms.cloudfiles"]);
			
			foreach ($data as $item) {
				SQL::insert("bigtree_caches", [
					"identifier" => "org.bigtreecms.cloudfiles",
					"key" => $item["path"],
					"value" => [
						"name" => $item["name"],
						"path" => $item["path"],
						"size" => $item["size"]
					]
				]);
			}
		}
		
		/*
			Function: uploadFile
				Creates a new file in the given container.
				Rackspace Cloud Files ignores "access" — public/private is controlled through the container only.

			Parameters:
				file - The file to upload.
				container - Container name.
				pointer - The full file path inside the container (if left empty the file's current name will be used and the root of the bucket)
				public - true to make publicly accessible, defaults to false (this setting is ignored in Rackspace Cloud Files and is ignored in Amazon S3 if the bucket's policy is set to public)
				type - MIME type (defaults to "text/plain")

			Returns:
				The URL of the file if successful.
		*/
		
		function uploadFile(string $file, string $container, ?string $pointer = null, bool $public = false): ?string
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
	}
