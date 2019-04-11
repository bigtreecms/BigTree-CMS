<?php
	/*
		Class: BigTree\Storage
			Facilitates the storage, deletion, and replacement of files (whether local or cloud stored).
	*/
	
	namespace BigTree;
	
	class Storage
	{
		
		public $AutoJPEG = false;
		public $Cloud;
		public $DisabledExtensionRegEx = '/\\.(exe|com|bat|php|rb|py|cgi|pl|sh|asp|aspx|phtml|pht|htaccess)/i';
		public $DisabledFileError = false;
		public $Service = "";
		public $Setting;
		public $Settings;
		
		/*
			Constructor:
				Retrieves the current desired service and image processing availability.
		*/
		
		public function __construct($force_local = false)
		{
			// Get an auto-saving setting
			$this->Setting = new Setting("bigtree-internal-storage");
			$this->Settings = &$this->Setting->Value;
			
			if (!$force_local && !empty($this->Settings->Service)) {
				if ($this->Settings["service"] == "s3" || $this->Settings["service"] == "amazon") {
					$this->Cloud = new CloudStorage\Amazon;
				} elseif ($this->Settings["service"] == "rackspace") {
					$this->Cloud = new CloudStorage\Rackspace;
				} elseif ($this->Settings["service"] == "google") {
					$this->Cloud = new CloudStorage\Google;
				}
			}
		}
		
		/*
			Function: delete
				Deletes a file from the active storage service.

			Parameters:
				file_location - The URL of the file.
			
			Returns:
				true if successful
		*/
		
		public function delete(string $file_location): bool
		{
			// Make sure we're using IPLs so we don't get it confused with cloud
			$file_location = str_replace([STATIC_ROOT, WWW_ROOT], ["{staticroot}", "{wwwroot}"], $file_location);
			
			// Cloud
			if (substr($file_location, 0, 4) == "http" || substr($file_location, 0, 5) == "https" || substr($file_location, 0, 2) == "//") {
				// Try to get the container and pointer
				$parts = explode("/", $file_location);
				$domain = $parts[2];
				$container = $parts[3];
				$pointer_parts = array_slice($parts, 4);
				
				// If this bucket is behind a CloudFront distribution, invalidate the cache and delete the file from the currently active bucket
				if (!empty($this->Cloud->Settings["amazon"]["cloudfront_distribution"]) && $this->Cloud->Settings["amazon"]["cloudfront_domain"] == $domain) {
					$file_name = implode("/", array_slice($parts, 3));
					
					$success = $this->Cloud->deleteFile($this->Settings->Container, $file_name);
					$this->Cloud->invalidateCache($file_name);
					
					SQL::query("DELETE FROM bigtree_caches WHERE `identifier` = 'org.bigtreecms.cloudfiles' AND `key` = ?", $file_name);
					
					return $success;
				}
				
				if (!empty($this->Settings["CDNDomain"]) && $this->Settings["CDNDomain"] == $domain) {
					$service = "amazon";
					$cloud = ($this->Settings["service"] == $service) ? $this->Cloud : new CloudStorage\Amazon;
					$container = $this->Settings["Container"];
					$pointer_parts = array_slice($parts, 3);
				} elseif (strpos($domain, "s3.amazonaws.com") !== false) {
					$service = "amazon";
					$cloud = ($this->Settings["service"] == $service) ? $this->Cloud : new CloudStorage\Amazon;
					
					if ($domain != "s3.amazonaws.com") {
						$domain_parts = explode(".", $domain);
						$container = $domain_parts[0];
						$pointer_parts = array_slice($parts, 3);
					}
				} elseif ($domain == "storage.googleapis.com") {
					$service = "google";
					$cloud = ($this->Settings["service"] == $service) ? $this->Cloud : new CloudStorage\Google;
				} else {
					$service = "rackspace";
					
					// Need to figure out the actual container
					$container = false;
					
					// If the current service is already Rackspace, we have an instance already
					$cloud = ($this->Settings["service"] == $service) ? $this->Cloud : new CloudStorage\Rackspace;
					
					foreach ($cloud->Settings["rackspace"]["container_cdn_urls"] as $c => $url) {
						if ($url == "http://$domain") {
							$container = $c;
						}
					}
					
					// Couldn't find the right container to delete from
					if (!$container) {
						return false;
					}
					
					$pointer_parts = array_slice($parts, 3);
				}
				
				$pointer = implode("/", $pointer_parts);
				
				// This is our primary storage service so we're going to delete from the cloudfiles cache as well
				if ($this->Settings["service"] == $service && $this->Settings["Container"] == $container) {
					SQL::delete("bigtree_caches", [
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $pointer
					]);
				}
				
				return $cloud->deleteFile($container, $pointer);
			}
			
			// Local
			return FileSystem::deleteFile(str_replace(["{wwwroot}", "{staticroot}"], SITE_ROOT, $file_location));
		}
		
		/*
			Function: formatBytes
				Formats bytes into larger units to make them more readable.
			
			Parameters:
				size - The number of bytes.
			
			Returns:
				A string with the number of bytes in kilobytes, megabytes, or gigabytes.
		*/
		
		public static function formatBytes(int $size): string
		{
			$units = [' B', ' KB', ' MB', ' GB', ' TB'];
			
			for ($i = 0; $size >= 1024 && $i < 4; $i++) {
				$size /= 1024;
			}
			
			return round($size, 2).$units[$i];
		}
		
		/*
			Function: getPOSTMaxSize
				Returns in bytes the maximum size of a POST.
		*/
		
		public static function getPOSTMaxSize(): int
		{
			$post_max_size = ini_get("post_max_size");
			
			if (!is_integer($post_max_size)) {
				$post_max_size = static::unformatBytes($post_max_size);
			}
			
			return $post_max_size;
		}
		
		/*
			Function: getUploadMaxFileSize
				Returns Apache's max file size value for use in forms.
		
			Returns:
				The integer value for setting a form's MAX_FILE_SIZE.
		*/
		
		public static function getUploadMaxFileSize(): int
		{
			$upload_max_filesize = ini_get("upload_max_filesize");
			
			if (!is_integer($upload_max_filesize)) {
				$upload_max_filesize = static::unformatBytes($upload_max_filesize);
			}
			
			$post_max_size = static::getPOSTMaxSize();
			
			if ($post_max_size < $upload_max_filesize) {
				$upload_max_filesize = $post_max_size;
			}
			
			return $upload_max_filesize;
		}
		
		/*
			Function: replace
				Stores a file to the current storage service and replaces any existing file with the same file_name.

			Parameters:
				local_file - The absolute path to the local file you wish to store.
				file_name - The file name at the storage end point.
				relative_path - The path (relative to SITE_ROOT or the bucket / container root) in which to store the file.
				remove_original - Whether to delete the local_file or not.
				force_local - Forces a local file replacement even if cloud storage is in use by default (defaults to false)

			Returns:
				The URL of the stored file if successful.
		*/
		
		public function replace(string $local_file, string $file_name, string $relative_path,
								bool $remove_original = true, bool $force_local = false): ?string
		{
			global $bigtree;
			
			// Make sure there are no path exploits
			$file_name = FileSystem::getSafePath($file_name);
			
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				unlink($local_file);
				
				return null;
			}
			
			// If we're auto converting images to JPG from PNG
			if ($this->AutoJPEG || $bigtree["config"]["image_force_jpeg"]) {
				$file_name = Image::convertPNGToJPEG($local_file, $file_name) ?: $file_name;
			}
			
			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path, "/")."/" : "files/";
			
			if ($this->Cloud && !$force_local) {
				$success = $this->Cloud->uploadFile($local_file, $this->Settings["Container"], $relative_path.$file_name, true);
				
				if ($success) {
					SQL::update("bigtree_caches", [
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $relative_path.$file_name
					], [
						"value" => [
							"name" => $file_name,
							"path" => $relative_path.$file_name,
							"size" => filesize($local_file)
						]
					]);
				}
				
				if ($remove_original) {
					unlink($local_file);
				}
				
				// If this bucket is behind a CloudFront distribution, invalidate the cache and return the CloudFront domain
				if (!empty($this->Cloud->Settings["amazon"]["cloudfront_distribution"])) {
					$this->Cloud->invalidateCache($relative_path.$file_name);
					$protocol = $this->Cloud->Settings["amazon"]["cloudfront_ssl"] ? "https" : "http";
					
					return $protocol."://".$this->Cloud->Settings["amazon"]["cloudfront_domain"]."/".$relative_path.$file_name;
				}
				
				return $success;
			} else {
				if ($remove_original) {
					$success = FileSystem::moveFile($local_file, SITE_ROOT.$relative_path.$file_name);
				} else {
					$success = FileSystem::copyFile($local_file, SITE_ROOT.$relative_path.$file_name);
				}
				
				if ($success) {
					return "{staticroot}".$relative_path.$file_name;
				} else {
					return false;
				}
			}
		}
		
		/*
			Function: store
				Stores a file to the current storage service and finds a unique filename if collisions exist.

			Parameters:
				local_file - The absolute path to the local file you wish to store.
				file_name - The desired file name at the storage end point.
				relative_path - The path (relative to SITE_ROOT or the bucket / container root) in which to store the file.
				remove_original - Whether to delete the local_file or not.
				prefixes - A list of file prefixes that also need to be accounted for when checking file name availability.

			Returns:
				The URL of the stored file if successful.
		*/
		
		public function store(string $local_file, string $file_name, string $relative_path,
							  bool $remove_original = true, array $prefixes = []): ?string
		{
			global $bigtree;
			
			// Make sure there are no path exploits
			$file_name = FileSystem::getSafePath($file_name);
			
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				unlink($local_file);
				
				return null;
			}
			
			// If we're auto converting images to JPG from PNG
			if ($this->AutoJPEG || $bigtree["config"]["image_force_jpeg"]) {
				$file_name = Image::convertPNGToJPEG($local_file, $file_name);
			}
			
			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path, "/")."/" : "files/";
			
			// Cloud Storage
			if ($this->Cloud) {
				// Clean up the file name
				$parts = pathinfo($file_name);
				$clean_name = Link::urlify($parts["filename"]);
				
				if (strlen($clean_name) > 50) {
					$clean_name = substr($clean_name, 0, 50);
				}
				
				// Best case name
				$file_name = $clean_name.".".strtolower($parts["extension"]);
				$x = 2;
				
				// Make sure we have a unique name
				while (empty($file_name) || SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_caches 
					   										  WHERE `identifier` = 'org.bigtreecms.cloudfiles' 
					   										  AND `key` = ?", $relative_path.$file_name)) {
					$file_name = $clean_name."-$x.".strtolower($parts["extension"]);
					$x++;
					
					// Check all the prefixes, make sure they don't exist either
					if (is_array($prefixes) && count($prefixes)) {
						$prefix_query = [];
						foreach ($prefixes as $prefix) {
							$prefix_query[] = "`key` = '".SQL::escape($relative_path.$prefix.$file_name)."'";
						}
						
						$exists = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_caches 
													WHERE identifier = 'org.bigtreecms.cloudfiles' 
													AND (".implode(" OR ", $prefix_query).")");
						if ($exists) {
							$file_name = false;
						}
					}
				}
				
				// Upload it
				$success = $this->Cloud->uploadFile($local_file, $this->Settings["Container"],
													$relative_path.$file_name, true);
				
				if ($success) {
					SQL::insert("bigtree_caches", [
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $relative_path.$file_name,
						"value" => [
							"name" => $file_name,
							"path" => $relative_path.$file_name,
							"size" => filesize($local_file)
						]
					]);
				}
				
				if ($remove_original) {
					unlink($local_file);
				}
				
				// If this bucket is behind a CloudFront distribution, invalidate the cache and return the CloudFront domain
				if (!empty($this->Cloud->Settings["amazon"]["cloudfront_distribution"])) {
					$this->Cloud->invalidateCache($relative_path.$file_name);
					$protocol = $this->Cloud->Settings["amazon"]["cloudfront_ssl"] ? "https" : "http";
					
					return $protocol."://".$this->Cloud->Settings["amazon"]["cloudfront_domain"]."/".$relative_path.$file_name;
				}
				
				return $success;
				
			// Local Storage
			} else {
				$safe_name = FileSystem::getAvailableFileName(SITE_ROOT.$relative_path, $file_name, $prefixes);
				
				if ($remove_original) {
					$success = FileSystem::moveFile($local_file, SITE_ROOT.$relative_path.$safe_name);
				} else {
					$success = FileSystem::copyFile($local_file, SITE_ROOT.$relative_path.$safe_name);
				}
				
				if ($success) {
					return "{staticroot}".$relative_path.$safe_name;
				} else {
					return null;
				}
			}
		}
		
		/*
			Function: unformatBytes
				Formats a string of kilobytes / megabytes / gigabytes back into bytes.
			
			Parameters:
				size - The string of (kilo/mega/giga)bytes.
			
			Returns:
				The number of bytes.
		*/
		
		public static function unformatBytes(string $size): int
		{
			$type = substr($size, -1, 1);
			$num = substr($size, 0, -1);
			
			if ($type == "M") {
				return $num * 1048576;
			} elseif ($type == "K") {
				return $num * 1024;
			} elseif ($type == "G") {
				return ($num * 1024 * 1024 * 1024);
			}
			
			return 0;
		}
		
	}
