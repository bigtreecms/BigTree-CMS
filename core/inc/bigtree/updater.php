<?
	/*
		Class: BigTreeUpdater
			Facilitates updating the CMS and extensions.
	*/

	class BigTreeUpdater {

		var $Connection = false;
		var $Method = false;

		/*
			Constructor:
				Determines which file replacement method to use.

			Parameter:
				extension - If updating an extension, the extension id (defaults to false)
		*/

		function __construct($extension = false) {
			// See if local will work
			$path = $extension ? SERVER_ROOT."extensions/$extension/" : SERVER_ROOT."core/";
			if (is_writable(SERVER_ROOT) && is_writable($path)) {
				$this->Method = "Local";

			// Can't use local, see what FTP methods are available
			} else {
				$ftp = new BigTreeFTP;
				$sftp = new BigTreeSFTP;
	
				if ($ftp->connect("localhost")) {
					$this->Connection = $ftp;
					$this->Method = "FTP";
				} elseif ($sftp->connect("localhost")) {
					$this->Connection = $ftp;
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
			include BigTree::path("inc/lib/pclzip.php");
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
			$contents = array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/update/"));
			foreach ($contents as $file) {
				if (is_dir($file)) {
					rmdir($file);	
				} else {
					unlink($file);
				}
			}
			rmdir(SERVER_ROOT."cache/update/");
			unlink(SERVER_ROOT."cache/update.zip");
		}

		/*
			Function: extract
				Extracts an update file into a temporary directory to prepare for update.

			Returns:
				true if successful
		*/

		function extract() {
			include BigTree::path("inc/lib/pclzip.php");
			$zip = new PclZip(SERVER_ROOT."cache/update.zip");

			// If the temporary update directory doesn't exist, create it
			if (!file_exists(SERVER_ROOT."cache/update/")) {
				mkdir(SERVER_ROOT."cache/update/");
				chmod(SERVER_ROOT."cache/update/",0777);
			}
			
			// Figure out what the initial directory is so we can remove it
			$contents = $zip->listContent();
			$initial_path = rtrim($contents[0]["filename"],"/");
			$zip->extract(PCLZIP_OPT_PATH,SERVER_ROOT."cache/update/",PCLZIP_OPT_REMOVE_PATH,$initial_path);
			
			// Error occurred extracting? Return false
			if ($zip->errorName() != "PCLZIP_ERR_NO_ERROR") {
				return false;
			}

			// Make sure everything extracted is 777 -- if we're writing as Apache we want bust permissions for the user.
			$contents = BigTree::directoryContents(SERVER_ROOT."cache/update/");
			foreach ($contents as $file) {
				chmod($file,0777);
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

		function ftpLogin($user,$password) {
			return $this->Connection->login($user,$password) ? true : false;
		}

		/*
			Function: getFTPRoot
				Attempts to determing the FTP directory in which BigTree can be found
			
			Parameters:
				user - Username for FTP/SFTP
				password - Password for FTP/SFTP

			Returns:
				The FTP directory if successful.
				false if not successful.
		*/

		function getFTPRoot($user,$password) {
			// Attempt to login
			$ftp->connect("localhost");
			if (!$ftp->login($user,$password)) {
				return false;
			}

			// Try to determine the FTP root.
			$ftp_root = "";
			$saved_root = BigTreeCMS::getSetting("")
			if ($admin->settingExists("bigtree-internal-ftp-upgrade-root") && $ftp->changeDirectory($cms->getSetting("bigtree-internal-ftp-upgrade-root")."core/inc/bigtree/")) {
				$ftp_root = $cms->getSetting("bigtree-internal-ftp-upgrade-root");
			} elseif ($ftp->changeDirectory(SERVER_ROOT."core/inc/bigtree/")) {
				$ftp_root = SERVER_ROOT;
			} elseif ($ftp->changeDirectory("/core/inc/bigtree")) {
				$ftp_root = "/";
			} elseif ($ftp->changeDirectory("/httpdocs/core/inc/bigtree")) {
				$ftp_root = "/httpdocs";
			} elseif ($ftp->changeDirectory("/public_html/core/inc/bigtree")) {
				$ftp_root = "/public_html";
			} elseif ($ftp->changeDirectory("/".str_replace(array("http://","https://"),"",DOMAIN)."inc/bigtree/")) {
				$ftp_root = "/".str_replace(array("http://","https://"),"",DOMAIN);
			}
			return $ftp_root;
		}

		/*
			Function: installFTP
				Installs an update via FTP/SFTP.

			Parameters:
				ftp_root - The FTP path to the root install directory for BigTree
				extension - If installing an extension, enter the extension ID (defaults to false)
		*/

		function installFTP($ftp_root,$extension = false) {
			$ftp_root = "/".trim($ftp_root,"/")."/";

			// Create backups folder
			$this->Connection->createDirectory($ftp_root."backups/");

			// Backup database
			$admin->backupDatabase(SERVER_ROOT."cache/backup.sql");
			$this->Connection->rename($ftp_root."cache/backup.sql",$ftp_root."backups/core-".BIGTREE_VERSION."/backup.sql");
			
			// Backup old core
			$this->Connection->rename($ftp_root."core/",$ftp_root."backups/core-".BIGTREE_VERSION."/");

			// Move new core into place
			$this->Connection->rename($ftp_root."cache/update/core/",$ftp_root."core/");
			
			$this->cleanup();
		}

		/*
			Function: installLocal
				Installs an update via local file replacement.

			Parameters:
				extension - If installing an extension, enter the extension ID (defaults to false)
		*/

		function installLocal($extension = false) {
			// Create backups folder
			if (!file_exists(SERVER_ROOT."backups/")) {
				mkdir(SERVER_ROOT."backups/");
				chmod(SERVER_ROOT."backups/",0777);
			}

			// Doing a core upgrade
			if ($extension === false) {
				// Move old core into backups
				rename(SERVER_ROOT."core/",SERVER_ROOT."backups/core-".BIGTREE_VERSION."/");
			
				// Backup database
				global $admin;
				$admin->backupDatabase(SERVER_ROOT."backups/core-".BIGTREE_VERSION."/backup.sql");
			
				// Move new core into place
				rename(SERVER_ROOT."cache/update/core/",SERVER_ROOT."core/");

			// Doing an extension upgrade
			} else {
				// Create a backups folder for this extension
				@mkdir(SERVER_ROOT."backups/extensions/");
				@mkdir(SERVER_ROOT."backups/extensions/$extension/");

				// Read manifest file for current version
				$current_manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$extension/manifest.json"),true);
				$old_version = $current_manifest["version"];

				// Move old extension into backups
				rename(SERVER_ROOT."extensions/$extension/",SERVER_ROOT."backups/extensions/$extension/$old_version/");

				// Move new extension into place
				rename(SERVER_ROOT."cache/update/",SERVER_ROOT."extensions/$extension/");
			}

			$this->cleanup();
		}
	}
?>