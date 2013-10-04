<?
	/*
		Class: BigTreeStorage
			Controls where files are stored (local and cloud storage)
	*/
	
	class BigTreeStorage {
		
		var $Service = "";
		var $S3,$S3Data,$S3Files;
		var $RSAuth,$RSConn,$RSContainers,$RSContainerData;
		var $DisabledExtensionRegEx = '/\\.(exe|com|bat|php|rb|py|cgi|pl|sh)$/i';
		
		/*
			Constructor:
				Retrieves the current desired service and image processing availability.
		*/
		
		function __construct() {
			global $cms,$admin;
			$ups = $cms->getSetting("bigtree-internal-storage");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($ups) || !$ups["service"]) {
				$this->Service = "local";
				$this->optipng = false;
				$this->jpegtran = false;
				$admin->createSetting(array(
					"id" => "bigtree-internal-storage",
					"encrypted" => "on",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-storage",array("service" => "local"));
			} else {
				$this->Service = $ups["service"];
				$this->optipng = $ups["optipng"];
				$this->jpegtran = $ups["jpegtran"];
				$this->RackspaceData = $ups["rackspace"];
				$this->S3Data = $ups["s3"];
				$this->S3Files = array();
			}
		}
		
		/*
			Function: delete
				Deletes a file from the active storage service.
			
			Parameters:
				file_location - The URL of the file.
		*/
		
		function delete($file_location) {
			if ($this->Service == "local") {
				return $this->deleteLocal($file_location);
			} elseif ($this->Service == "s3") {
				return $this->deleteS3($file_location);
			} elseif ($this->Service == "rackspace") {
				return $this->deleteRackspace($file_location);
			} else {
				die("BigTree Critical Error: Unknown Storage Service In Effect");
			}
		}
		
		/*
			Function: deleteLocal
				Private function for the delete call when local storage is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteLocal($file_location) {
			unlink(str_replace(array("{wwwroot}","{staticroot}"),SITE_ROOT,$file_location));
		}
		
		/*
			Function: deleteS3
				Private function for the delete call when Amazon S3 is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteS3($file_location) {
			global $cms;
			
			if (!$this->S3) {
				$this->S3 = new S3($this->S3Data["keys"]["access_key_id"],$this->S3Data["keys"]["secret_access_key"],true);
			}
			
			// Take apart the file location since it's a full path.
			if (substr($file_location,0,7) == "http://") {
				$file_location = substr($file_location,7); // Cut off http://
			} else {
				$file_location = substr($file_location,8); // Cut off https://				
			}
			$file_location = substr($file_location,strpos($file_location,"/") + 1); // Cut off up to the first /.
			$bucket = substr($file_location,0,strpos($file_location,"/")); // Get the bucket.
			$file = substr($file_location,strlen($bucket) + 1); // Get the file.
			$this->S3->deleteObject($bucket,$file);
		}
		
		/*
			Function: deleteRackspace
				Private function for the delete call when Rackspace Cloud Files is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteRackspace($file_location) {
			global $cms;
			
			if (!$this->Rackspace) {
				$keys = $this->RackspaceData["keys"];
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $this->RackspaceData["containers"];
			}
			
			$parts = BigTree::pathInfo($file_location);
			
			foreach ($this->RSContainers as $key => $val) {
				if ($val == $parts["dirname"]) {
					$path = $key;
				}
			}
			
			$container = $this->RSConn->get_container($path);
			$container->delete_object($parts["basename"]);
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
				The URL to the stored file.
		*/
		
		function replace($local_file,$file_name,$relative_path,$remove_original = true) {
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				return false;
			}
			if ($this->Service == "local") {
				if (!$relative_path) {
					$relative_path = "files/";
				}
				$relative_path = rtrim($relative_path,"/")."/";
				return $this->replaceLocal($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "s3") {
				return $this->replaceS3($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "rackspace") {
				return $this->replaceRackspace($local_file,$file_name,$relative_path,$remove_original);
			} else {
				die("BigTree Critical Error: Unknown Storage Service In Effect");
			}
		}
		
		/*
			Function: replaceLocal
				Private function for the replace call when local storage is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceLocal($local_file,$file_name,$relative_path,$remove_original) {
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
		
		/*
			Function: replaceS3
				Private function for the replace call when Amazon S3 is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceS3($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->S3) {
				$this->S3 = new S3($this->S3Data["keys"]["access_key_id"],$this->S3Data["keys"]["secret_access_key"]);
			}
			
			$destination = rtrim($relative_path,"/")."/".$file_name;
			$this->S3->putObjectFile($local_file,$this->S3Data["bucket"],$destination,S3::ACL_PUBLIC_READ);
			
			// Update the list of files in this bucket locally.
			$this->S3Files[$destination] = true;
			
			// Remove the original file we were storing.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return "http://s3.amazonaws.com/".$this->S3Data["bucket"]."/$destination";
		}
		
		/*
			Function: replaceRackspace
				Private function for the replace call when Rackspace Cloud Files is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceRackspace($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->Rackspace) {
				$keys = $this->RackspaceData["keys"];
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $this->RackspaceData["containers"];
			}
			
			$relative_path = str_replace("/","-",rtrim($relative_path,"/"));
			
			// If we don't have a bucket for this path yet, make one.
			$url = $this->RSContainers[$relative_path];
			if (!$url) {
				$container = $this->RSConn->create_container($relative_path);
				$url = $container->make_public();

				$this->RSContainers[$relative_path] = $url;
				$this->saveSettings();
			} else {
				$container = $this->RSConn->get_container($relative_path);
			}
			
			// Create the object
			$object = $container->create_object($file_name);
			$object->load_from_filename($local_file);
			
			// Update the list of files in this container locally.
			$existing_files[] = $file_name;
			$this->RSContainerData[$relative_path] = $existing_files;
			
			// Remove the original file we were storing.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return $url."/".$file_name;
		}
		
		/*
			Function: saveSettings
				Saves the local data back to the bigtree-internal-storage setting.
		*/
		
		protected function saveSettings() {
			$admin = new BigTreeAdmin;
			$admin->updateSettingValue("bigtree-internal-storage",array(
				"service" => $this->Service,
				"s3" => array(
					"keys" => $this->S3Data["keys"],
					"bucket" => $this->S3Data["bucket"],
					"files" => $this->S3Data["files"]
				),
				"rackspace" => array(
					"keys" => $this->RackspaceData["keys"],
					"containers" => $this->RSContainers
				)
			));
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
				The URL to the stored file.
		*/
		
		function store($local_file,$file_name,$relative_path,$remove_original = true) {
			// If the file name ends in a disabled extension, fail.
			if (preg_match($this->DisabledExtensionRegEx, $file_name)) {
				$this->DisabledFileError = true;
				return false;
			}
			if ($this->Service == "local") {
				if (!$relative_path) {
					$relative_path = "files/";
				}
				$relative_path = rtrim($relative_path,"/")."/";
				return $this->storeLocal($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "s3") {
				return $this->storeS3($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "rackspace") {
				return $this->storeRackspace($local_file,$file_name,$relative_path,$remove_original);
			} else {
				die("BigTree Critical Error: Unknown Storage Service In Effect");
			}
		}
		
		/*
			Function: storeLocal
				Private function for the store call when local storage is active.
			
			See Also:
				<store>
		*/
		
		private function storeLocal($local_file,$file_name,$relative_path,$remove_original) {
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
		
		/*
			Function: storeS3
				Private function for the store call when Amazon S3 is active.
			
			See Also:
				<store>
		*/
		
		private function storeS3($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->S3) {
				$this->S3 = new S3($this->S3Data["keys"]["access_key_id"],$this->S3Data["keys"]["secret_access_key"]);
			}
			
			// Check to see if this is a unique file name for this bucket, if not, get one.
			if (!count($this->S3Files)) {
				$this->S3Files = $this->S3->getBucket($this->S3Data["bucket"]);
			}
			
			// Get a nice clean file name
			$parts = BigTree::pathInfo($file_name);
			$clean_name = $cms->urlify($parts["filename"]);
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name,0,50);
			}
			$file_name = $clean_name.".".$parts["extension"];
			
			// Loop until we get a good file name.
			$x = 2;
			$destination = rtrim($relative_path,"/")."/".$file_name;
			while ($this->S3Files[$destination]) {
				$destination = rtrim($relative_path,"/")."/".$clean_name."-$x.".$parts["extension"];
				$x++;
			}
			
			$this->S3->putObjectFile($local_file,$this->S3Data["bucket"],$destination,S3::ACL_PUBLIC_READ);
			
			// Update the list of files in this bucket locally.
			$this->S3Files[$destination] = true;
			
			// Remove the original file we were storing.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return "http://s3.amazonaws.com/".$this->S3Data["bucket"]."/$destination";
		}
		
		/*
			Function: storeRackspace
				Private function for the store call when Rackspace Cloud Files is active.
			
			See Also:
				<store>
		*/
		
		private function storeRackspace($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
						
			if (!$this->Rackspace) {
				$keys = $this->RackspaceData["keys"];
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $this->RackspaceData["containers"];
			}

			$relative_path = str_replace("/","-",rtrim($relative_path,"/"));
			
			// If we don't have a bucket for this path yet, make one.
			$url = $this->RSContainers[$relative_path];
			if (!$url) {
				$container = $this->RSConn->create_container($relative_path);
				$url = $container->make_public();

				$this->RSContainers[$relative_path] = $url;
				$this->saveSettings();
			} else {
				$container = $this->RSConn->get_container($relative_path);
			}
			
			// Check to see if this is a unique file name for this bucket, if not, get one.
			$existing_files = $this->RSContainerData[$relative_path];
			if (!$existing_files) {
				$existing_files = $container->list_objects();
				$this->RSContainerData[$relative_path] = $existing_files;
			}
			
			// Get a nice clean file name
			$parts = BigTree::pathInfo($file_name);
			$clean_name = $cms->urlify($parts["filename"]);
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name,0,50);
			}
			$file_name = $clean_name.".".$parts["extension"];
			
			// Loop until we get a good file name.
			$x = 2;
			$original = $file_name;
			while (in_array($file_name,$existing_files)) {
				$file_name = $clean_name."-$x.".$parts["extension"];
				$x++;
			}
			
			// Create the object
			$object = $container->create_object($file_name);
			$object->load_from_filename($local_file);
			
			// Update the list of files in this container locally.
			$existing_files[] = $file_name;
			$this->RSContainerData[$relative_path] = $existing_files;
			
			// Remove the original file we were storing.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return $url."/".$file_name;
		}
	}

	// Backwards compatibility
	class BigTreeUploadService extends BigTreeStorage {
		function upload($local_file,$file_name,$relative_path,$remove_original = true) {
			return $this->store($local_file,$file_name,$relative_path,$remove_original);
		}
	}
?>