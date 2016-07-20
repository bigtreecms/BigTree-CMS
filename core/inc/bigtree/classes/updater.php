<?php
	/*
		Class: BigTree\Updater
			Facilitates updating the CMS and extensions.
	*/
	
	namespace BigTree;
	use PclZip;
	
	class Updater {
		
		public $Connection;
		public $Extension = false;
		public $Method = false;
		
		/*
			Constructor:
				Determines which file replacement method to use.

			Parameter:
				extension - If updating an extension, the extension id (defaults to false)
		*/
		
		function __construct($extension = false) {
			$this->Extension = $extension;
			
			// See if local will work
			$path = $extension ? SERVER_ROOT."extensions/$extension/" : SERVER_ROOT."core/";
			if (is_writable(SERVER_ROOT) && is_writable($path)) {
				$this->Method = "Local";
			} else {
				// Can't use local, see what FTP methods are available
				$ftp = new FTP;
				$sftp = new SFTP;
				
				if ($ftp->connect("localhost")) {
					$this->Connection = $ftp;
					$this->Method = "FTP";
				} elseif ($sftp->connect("localhost")) {
					$this->Connection = $sftp;
					$this->Method = "SFTP";
				}
			}
		}
		
		/*
			Function: checkZip
				Checks the update zip file for integrity.

			Returns:
				true if the file isn't corrupt
		*/
		
		function checkZip() {
			include_once SERVER_ROOT."core/inc/lib/pclzip.php";
			$zip = new PclZip(SERVER_ROOT."cache/update.zip");
			$zip->listContent();
			
			if ($zip->errorName() != "PCLZIP_ERR_NO_ERROR") {
				return false;
			}
			
			return true;
		}
		
		/*
			Function: cleanup
				Removes update related files and directories.
		*/
		
		function cleanup() {
			if (file_exists(SERVER_ROOT."cache/update/")) {
				FileSystem::deleteDirectory(SERVER_ROOT."cache/update/");
			}
			
			FileSystem::deleteFile(SERVER_ROOT."cache/update.zip");
		}
		
		/*
			Function: extract
				Extracts an update file into a temporary directory to prepare for update.

			Returns:
				true if successful
		*/
		
		function extract() {
			include_once SERVER_ROOT."core/inc/lib/pclzip.php";
			$zip = new PclZip(SERVER_ROOT."cache/update.zip");
			
			// If the temporary update directory doesn't exist, create it
			FileSystem::createDirectory(SERVER_ROOT."cache/update/");
			
			// Figure out if we have just a single directory at the root
			$zip_root = $this->zipRoot($zip);
			
			if ($zip_root) {
				$zip->extract(PCLZIP_OPT_PATH, SERVER_ROOT."cache/update/", PCLZIP_OPT_REMOVE_PATH, $zip_root);
			} else {
				$zip->extract(PCLZIP_OPT_PATH, SERVER_ROOT."cache/update/");
			}
			
			// Error occurred extracting? Return false
			if ($zip->errorName() != "PCLZIP_ERR_NO_ERROR") {
				return false;
			}
			
			// Make sure everything extracted is 777 -- if we're writing as Apache we want bust permissions for the user.
			$contents = FileSystem::getDirectoryContents(SERVER_ROOT."cache/update/");
			
			foreach ($contents as $file) {
				chmod($file, 0777);
			}
			
			return true;
		}
		
		/*
			Function: ftpLogin
				Makes an FTP connection to localhost.

			Parameters:
				user - Username
				password - Password

			Returns:
				true if successful.
		*/
		
		function ftpLogin($user, $password) {
			return $this->Connection->login($user, $password) ? true : false;
		}
		
		/*
			Function: getFTPRoot
				Attempts to determing the FTP directory in which BigTree can be found

			Returns:
				The FTP directory if successful.
				false if not successful.
		*/
		
		function getFTPRoot() {
			// Try to determine the FTP root.
			$ftp_root = false;
			$saved_root = Setting::value("bigtree-internal-ftp-upgrade-root");
			
			if ($saved_root !== false && $this->Connection->changeDirectory($saved_root."core/inc/bigtree/")) {
				$ftp_root = $saved_root;
			} elseif ($this->Connection->changeDirectory(SERVER_ROOT."core/inc/bigtree/")) {
				$ftp_root = SERVER_ROOT;
			} elseif ($this->Connection->changeDirectory("/core/inc/bigtree")) {
				$ftp_root = "/";
			} elseif ($this->Connection->changeDirectory("/httpdocs/core/inc/bigtree")) {
				$ftp_root = "/httpdocs";
			} elseif ($this->Connection->changeDirectory("/public_html/core/inc/bigtree")) {
				$ftp_root = "/public_html";
			} elseif ($this->Connection->changeDirectory("/".str_replace(array("http://", "https://"), "", DOMAIN)."inc/bigtree/")) {
				$ftp_root = "/".str_replace(array("http://", "https://"), "", DOMAIN);
			}
			
			return $ftp_root;
		}
		
		/*
			Function: installFTP
				Installs an update via FTP/SFTP.

			Parameters:
				ftp_root - The FTP path to the root install directory for BigTree
		*/
		
		function installFTP($ftp_root) {
			$ftp_root = "/".trim($ftp_root, "/")."/";
			
			// Create backups folder
			$this->Connection->createDirectory($ftp_root."backups/");
			
			// Doing a core upgrade
			if ($this->Extension === false) {
				// Backup database
				SQL::backup(SERVER_ROOT."cache/backup.sql");
				$this->Connection->rename($ftp_root."cache/backup.sql", $ftp_root."backups/core-".BIGTREE_VERSION."/backup.sql");
				
				// Backup old core
				$this->Connection->rename($ftp_root."core/", $ftp_root."backups/core-".BIGTREE_VERSION."/");
				
				// Move new core into place
				$this->Connection->rename($ftp_root."cache/update/core/", $ftp_root."core/");
				// Doing an extension upgrade
			} else {
				$extension = $this->Extension;
				
				// Create a backups folder for this extension
				$this->Connection->createDirectory($ftp_root."backups/extensions/");
				$this->Connection->createDirectory($ftp_root."backups/extensions/$extension/");
				
				// Read manifest file for current version
				$current_manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$extension/manifest.json"), true);
				$old_version = $current_manifest["version"];
				
				// Get a unique directory name
				$old_version = FileSystem::getAvailableFileName(SERVER_ROOT."backups/extensions/$extension/", $old_version);
				
				// Move old extension into backups
				$this->Connection->rename($ftp_root."extensions/$extension/", $ftp_root."backups/extensions/$extension/$old_version/");
				
				// Move new extension into place
				$this->Connection->rename($ftp_root."cache/update/", $ftp_root."extensions/$extension/");
			}
			
			$this->cleanup();
		}
		
		/*
			Function: installLocal
				Installs an update via local file replacement.
		*/
		
		function installLocal() {
			// Create backups folder
			FileSystem::createDirectory(SERVER_ROOT."backups/");
			
			// Doing a core upgrade
			if ($this->Extension === false) {
				// Move old core into backups
				rename(SERVER_ROOT."core/", SERVER_ROOT."backups/core-".BIGTREE_VERSION."/");
				
				// Backup database
				global $admin;
				$admin->backupDatabase(SERVER_ROOT."backups/core-".BIGTREE_VERSION."/backup.sql");
				
				// Move new core into place
				rename(SERVER_ROOT."cache/update/core/", SERVER_ROOT."core/");
				
				// Doing an extension upgrade
			} else {
				$extension = $this->Extension;
				
				// Create a backups folder for this extension
				FileSystem::createDirectory(SERVER_ROOT."backups/extensions/$extension/");
				
				// Read manifest file for current version
				$current_manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$extension/manifest.json"), true);
				$old_version = $current_manifest["version"];
				
				// Get a unique directory name
				$old_version = FileSystem::getAvailableFileName(SERVER_ROOT."backups/extensions/$extension/", $old_version);
				
				// Move old extension into backups
				rename(SERVER_ROOT."extensions/$extension/", SERVER_ROOT."backups/extensions/$extension/$old_version/");
				
				// Move new extension into place
				rename(SERVER_ROOT."cache/update/", SERVER_ROOT."extensions/$extension/");
			}
			
			$this->cleanup();
		}
		
		/*
			Function: unzip
				Unzips a file.
			
			Parameters:
				file - Location of the file to unzip
				destination - The full path to unzip the file's contents to.
		*/
		
		static function unzip($file, $destination) {
			// If we can't write the output directory, we're not getting anywhere.
			if (!FileSystem::getDirectoryWritability($destination)) {
				return false;
			}
			
			// Up the memory limit for the unzip.
			ini_set("memory_limit", "512M");
			
			$destination = rtrim($destination)."/";
			FileSystem::createDirectory($destination);
			
			// If we have the built in ZipArchive extension, use that.
			if (class_exists("ZipArchive")) {
				$z = new \ZipArchive;
				
				if (!$z->open($file)) {
					// Bad zip file.
					return false;
				}
				
				for ($i = 0; $i < $z->numFiles; $i++) {
					if (!$info = $z->statIndex($i)) {
						// Unzipping the file failed for some reason.
						return false;
					}
					
					// If it's a directory, ignore it. We'll create them in putFile.
					if (substr($info["name"], -1) == "/") {
						continue;
					}
					
					// Ignore __MACOSX and all it's files.
					if (substr($info["name"], 0, 9) == "__MACOSX/") {
						continue;
					}
					
					$content = $z->getFromIndex($i);
					if ($content === false) {
						// File extraction failed.
						return false;
					}
					FileSystem::createFile($destination.$file["name"], $content);
				}
				
				$z->close();
				
				return true;
				
				// Fall back on PclZip if we don't have the "native" version.
			} else {
				// WordPress claims this could be an issue, so we'll make sure multibyte encoding isn't overloaded.
				if (ini_get('mbstring.func_overload') && function_exists('mb_internal_encoding')) {
					$previous_encoding = mb_internal_encoding();
					mb_internal_encoding('ISO-8859-1');
				}
				
				$z = new PclZip($file);
				$archive = $z->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
				
				// If we saved a previous encoding, reset it now.
				if (isset($previous_encoding)) {
					mb_internal_encoding($previous_encoding);
					unset($previous_encoding);
				}
				
				// If it's not an array, it's not a good zip. Also, if it's empty it's not a good zip.
				if (!is_array($archive) || !count($archive)) {
					return false;
				}
				
				foreach ($archive as $item) {
					// If it's a directory, ignore it. We'll create them in putFile.
					if ($item["folder"]) {
						continue;
					}
					
					// Ignore __MACOSX and all it's files.
					if (substr($item["filename"], 0, 9) == "__MACOSX/") {
						continue;
					}
					
					FileSystem::createFile($destination.$item["filename"], $item["content"]);
				}
				
				return true;
			}
		}
		
		/*
			Function: zipRoot
				Returns the root of a zip file (if the root is simply a folder)

			Parameters:
				zip - PclZip instance

			Returns:
				A folder name or false if the root contains more than just a folder.
		*/
		
		static function zipRoot(PclZip $zip) {
			$contents = $zip->listContent();
			$root_count = 0;
			$root = false;
			foreach ($contents as $content) {
				$file = rtrim($content["filename"], "/");
				$pieces = explode("/", $file);
				if (count($pieces) == 1) {
					$root_count++;
					$root = $file;
				}
			}
			if ($root_count == 1) {
				return $root;
			}
			
			return false;
		}
	}