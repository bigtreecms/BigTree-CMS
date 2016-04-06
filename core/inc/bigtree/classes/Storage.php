<?php
	/*
		Class: BigTree\Storage
			Facilitates the storage, deletion, and replacement of files (whether local or cloud stored).
	*/

	namespace BigTree;

	use BigTree;

	class Storage {

		var $AutoJPEG = false;
		var $DisabledFileError = false;
		var $DisabledExtensionRegEx = '/\\.(exe|com|bat|php|rb|py|cgi|pl|sh|asp|aspx)$/i';
		var $Service = "";
		var $Cloud = false;
		var $Setting;
		var $Settings;

		/*
			Constructor:
				Retrieves the current desired service and image processing availability.
		*/

		function __construct() {
			// Get an auto-saving setting
			$this->Setting = new Setting("bigtree-internal-storage", true, true);
			$this->Settings &= $this->Setting->Value;

			if (!empty($this->Settings["service"])) {
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
		*/

		function delete($file_location) {
			// Make sure we're using IPLs so we don't get it confused with cloud
			$file_location = str_replace(array(STATIC_ROOT,WWW_ROOT),array("{staticroot}","{wwwroot}"),$file_location);

			// Cloud
			if (substr($file_location,0,4) == "http" || substr($file_location,0,5) == "https" || substr($file_location,0,2) == "//") {
				// Try to get the container and pointer
				$parts = explode("/",$file_location);
				$domain = $parts[2];
				$container = $parts[3];
				$pointer_parts = array_slice($parts,4);

				if ($domain == "s3.amazonaws.com") {
					$service = "amazon";
					$cloud = ($this->Settings["service"] == $service) ? $this->Cloud : new CloudStorage\Amazon;
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

					$pointer_parts = array_slice($parts,3);
				}

				$pointer = implode("/",$pointer_parts);

				// This is our primary storage service so we're going to delete from the cloudfiles cache as well
				if ($this->Settings["service"] == $service && $this->Settings["Container"] == $container) {
					SQL::delete("bigtree_caches", array(
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $pointer
					));
				}

				return $cloud->deleteFile($container,$pointer);
			}

			// Local
			return FileSystem::deleteFile(str_replace(array("{wwwroot}","{staticroot}"),SITE_ROOT,$file_location));
		}

		/*
			Function: replace
				Stores a file to the current storage service and replaces any existing file with the same file_name.

			Parameters:
				local_file - The absolute path to the local file you wish to store.
				file_name - The file name at the storage end point.
				relative_path - The path (relative to SITE_ROOT or the bucket / container root) in which to store the file.
				remove_original - Whether to delete the local_file or not.

			Returns:
				The URL of the stored file.
		*/

		function replace($local_file,$file_name,$relative_path,$remove_original = true) {
			global $bigtree;

			// Make sure there are no path exploits
			$file_name = FileSystem::getSafePath($file_name);

			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				unlink($local_file);

				return false;
			}

			// If we're auto converting images to JPG from PNG
			if ($this->AutoJPEG || $bigtree["config"]["image_force_jpeg"]) {
				$file_name = Image::convertPNGToJPEG($local_file, $file_name) ?: $file_name;
			}

			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path,"/")."/" : "files/";

			if ($this->Cloud) {
				$success = $this->Cloud->uploadFile($local_file,$this->Settings["Container"],$relative_path.$file_name,true);

				if ($success) {
					SQL::update("bigtree_caches",
						array(
							"identifier" => "org.bigtreecms.cloudfiles",
							"key" => $relative_path.$file_name
						),
						array(
							"value" => array(
								"name" => $file_name,
								"path" => $relative_path.$file_name,
								"size" => filesize($local_file)
							)
						)
					);
				}

				if ($remove_original) {
					unlink($local_file);
				}

				return $success;
			} else {
				if ($remove_original) {
					$success = FileSystem::moveFile($local_file,SITE_ROOT.$relative_path.$file_name);
				} else {
					$success = FileSystem::copyFile($local_file,SITE_ROOT.$relative_path.$file_name);
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
				The URL of the stored file.
		*/

		function store($local_file,$file_name,$relative_path,$remove_original = true,$prefixes = array()) {
			global $bigtree;

			// Make sure there are no path exploits
			$file_name = FileSystem::getSafePath($file_name);

			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				unlink($local_file);

				return false;
			}

			// If we're auto converting images to JPG from PNG
			if ($this->AutoJPEG || $bigtree["config"]["image_force_jpeg"]) {
				$file_name = Image::convertPNGToJPEG($local_file, $file_name);
			}

			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path,"/")."/" : "files/";

			// Cloud Storage
			if ($this->Cloud) {
				// Clean up the file name
				$parts = BigTree::pathInfo($file_name);
				$clean_name = Link::urlify($parts["filename"]);
				if (strlen($clean_name) > 50) {
					$clean_name = substr($clean_name,0,50);
				}

				// Best case name
				$file_name = $clean_name.".".strtolower($parts["extension"]);
				$x = 2;

				// Make sure we have a unique name
				while (!$file_name || SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_caches 
					   									WHERE `identifier` = 'org.bigtreecms.cloudfiles' 
					   									AND `key` = ?", $relative_path.$file_name)) {
					$file_name = $clean_name."-$x.".strtolower($parts["extension"]);
					$x++;

					// Check all the prefixes, make sure they don't exist either
					if (is_array($prefixes) && count($prefixes)) {
						$prefix_query = array();
						foreach ($prefixes as $prefix) {
							$prefix_query[] = "`key` = '".SQL::escape($relative_path.$prefix.$file_name)."'";
						}

						$exists = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_caches 
													WHERE identifier = 'org.bigtreecms.cloudfiles' 
													AND (".implode(" OR ",$prefix_query).")");
						if ($exists) {
							$file_name = false;
						}
					}
				}

				// Upload it
				$success = $this->Cloud->uploadFile($local_file,$this->Settings["Container"],$relative_path.$file_name,true);

				if ($success) {
					SQL::insert("bigtree_caches", array(
						"identifier" => "org.bigtreecms.cloudfiles",
						"key" => $relative_path.$file_name,
						"value" => array(
							"name" => $file_name,
							"path" => $relative_path.$file_name,
							"size" => filesize($local_file)
						)
					));
				}

				if ($remove_original) {
					unlink($local_file);
				}

				return $success;

			// Local Storage
			} else {
				$safe_name = FileSystem::getAvailableFileName(SITE_ROOT.$relative_path,$file_name,$prefixes);

				if ($remove_original) {
					$success = FileSystem::moveFile($local_file,SITE_ROOT.$relative_path.$safe_name);
				} else {
					$success = FileSystem::copyFile($local_file,SITE_ROOT.$relative_path.$safe_name);
				}

				if ($success) {
					return "{staticroot}".$relative_path.$safe_name;
				} else {
					return false;
				}
			}
		}

	}
