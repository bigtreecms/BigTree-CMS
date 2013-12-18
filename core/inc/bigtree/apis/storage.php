<?
	/*
		Class: BigTreeStorage
			Controls where files are stored (local and cloud storage)
	*/
	
	class BigTreeStorage {
		
		var $AutoJPEG = false;
		var $DisabledExtensionRegEx = '/\\.(exe|com|bat|php|rb|py|cgi|pl|sh)$/i';
		var $Service = "";
		var $Cloud = false;

		/*
			Constructor:
				Retrieves the current desired service and image processing availability.
		*/
		
		function __construct() {
			global $cms,$admin;
			$settings = $cms->getSetting("bigtree-internal-storage");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($settings) || !$settings["service"]) {
				$this->Service = "local";
				$this->optipng = false;
				$this->jpegtran = false;
				$this->Container = "";
				$this->Files = array();
				$admin->createSetting(array(
					"id" => "bigtree-internal-storage",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-storage",array("service" => "local"));
			} else {
				$this->Service = $settings["service"];
				$this->optipng = isset($settings["optipng"]) ? $settings["optipng"] : false;
				$this->jpegtran = isset($settings["jpegtran"]) ? $settings["jpegtran"] : false;
				$this->Container = $settings["container"];
				$this->Files = $settings["files"];
			}
			if ($this->Service == "s3" || $this->Service == "amazon") {
				$this->Cloud = new BigTreeCloudStorage("amazon");
			} elseif ($this->Service == "rackspace") {
				$this->Cloud = new BigTreeCloudStorage("rackspace");
			} elseif ($this->Service == "google") {
				$this->Cloud = new BigTreeCloudStorage("google");
			}
		}

		function __destruct() {
			$admin = new BigTreeAdmin;
			$admin->updateSettingValue("bigtree-internal-storage",array(
				"service" => $this->Service,
				"optipng" => $this->optipng,
				"jpegtran" => $this->jpegtran,
				"container" => $this->Container,
				"files" => $this->Files
			));
		}

		/*
			Function: convertJPEG
				Internal function for turning PNGs uploaded into JPG
		*/

		function convertJPEG($file,$name) {
			global $bigtree;
			
			// Try to figure out what this file is
			list($iwidth,$iheight,$itype,$iattr) = @getimagesize($file);
			
			if (($this->AutoJPEG || $bigtree["config"]["image_force_jpeg"]) && $itype == IMAGETYPE_PNG) {
				// See if this PNG has any alpha channels, if it does we're not doing a JPG conversion.
				$alpha = ord(@file_get_contents($file,null,null,25,1));
				if ($alpha != 4 && $alpha != 6) {
					// Convert the PNG to JPG
					$source = imagecreatefrompng($file);
					imagejpeg($source,$file,$bigtree["config"]["image_quality"]);
					imagedestroy($source);
	
					// If they originally uploaded a JPG we rotated into a PNG, we don't want to change the desired filename, but if they uploaded a PNG the new file should be JPG
					if (strtolower(substr($name,-3,3)) == "png") {
						$name = substr($name,0,-3)."jpg";
					}
				}
			}

			return $name;
		}
		
		/*
			Function: delete
				Deletes a file from the active storage service.
			
			Parameters:
				file_location - The URL of the file.
		*/
		
		function delete($file_location) {
			if ($this->Cloud) {
				unset($this->Files[$file_location]);
				return $this->Cloud->deleteFile($this->Container,$file_location);
			}
			unlink(str_replace(array("{wwwroot}","{staticroot}"),SITE_ROOT,$file_location));
			return true;
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
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				return false;
			}

			// If we're auto converting images to JPG from PNG
			$file_name = $this->convertJPEG($local_file,$file_name);
			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path,"/")."/" : "files/";

			if ($this->Cloud) {
				$success = $this->Cloud->uploadFile($local_file,$this->Container,$relative_path.$file_name);
				if ($remove_original) {
					unlink($local_file);
				}
				if ($success) {
					$this->Files[$relative_path.$file_name] = array("name" => $file_name,"path" => $relative_path.$file_name,"size" => filesize($local_file));
				}
				return $success;
			} else {
				if ($remove_original) {
					$success = BigTree::moveFile($local_file,SITE_ROOT.$relative_path.$file_name);
				} else {
					$success = BigTree::copyFile($local_file,SITE_ROOT.$relative_path.$file_name);
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
			
			Returns:
				The URL of the stored file.
		*/
		
		function store($local_file,$file_name,$relative_path,$remove_original = true) {
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				return false;
			}

			// If we're auto converting images to JPG from PNG
			$file_name = $this->convertJPEG($local_file,$file_name);
			// Enforce trailing slashe on relative_path
			$relative_path = $relative_path ? rtrim($relative_path,"/")."/" : "files/";

			if ($this->Cloud) {
				// Clean up the file name
				global $cms;
				$parts = self::pathInfo($file_name);
				$clean_name = $cms->urlify($parts["filename"]);
				if (strlen($clean_name) > 50) {
					$clean_name = substr($clean_name,0,50);
				}
				$original_name = $file_name = $clean_name.".".strtolower($parts["extension"]);
				$x = 2;
				// Make sure we have a unique name
				while (isset($this->Files[$relative_path.$file_name])) {
					$file_name = $original_name."-".$x;
					$x++;
				}
				// Upload it
				$success = $this->Cloud->uploadFile($local_file,$this->Container,$relative_path.$file_name);
				$this->Files[$relative_path.$file_name] = array("name" => $file_name,"path" => $relative_path.$file_name,"size" => filesize($local_file));
				if ($remove_original) {
					unlink($local_file);
				}
				return $success;
			} else {
				$safe_name = BigTree::getAvailableFileName(SITE_ROOT.$relative_path,$file_name);
				if ($remove_original) {
					$success = BigTree::moveFile($local_file,SITE_ROOT.$relative_path.$safe_name);
				} else {
					$success = BigTree::copyFile($local_file,SITE_ROOT.$relative_path.$safe_name);
				}
				if ($success) {
					return "{staticroot}".$relative_path.$safe_name;
				} else {
					return false;
				}
			}
		}
	}

	// Backwards compatibility
	class BigTreeUploadService extends BigTreeStorage {
		function upload($local_file,$file_name,$relative_path,$remove_original = true) {
			return $this->store($local_file,$file_name,$relative_path,$remove_original);
		}
	}
?>