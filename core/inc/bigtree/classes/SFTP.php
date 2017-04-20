<?php
	/*
		Class: BigTree\SFTP
			A SFTP class based on (and requiring) phpseclib.
			Meant to be method-compatible with BigTree\FTP.
	*/
	
	namespace BigTree;
	
	set_include_path(get_include_path().PATH_SEPARATOR.SERVER_ROOT.'core/inc/lib/phpseclib/phpseclib');
	require_once("Net/SFTP.php");
	
	class SFTP {
		
		/** @var \Net_SFTP */
		public $Connection;
		
		/*
			Function: changeToParentDirectory
				Changes the current working directory to its parent.

			Returns:
				true if successful
		*/
		
		function changeToParentDirectory(): bool {
			return $this->Connection->chdir("..");
		}
		
		/*
			Function: changeDirectory
				Changes the current working directory to given path.

			Parameters:
				path - Full directory path to change to.
			Returns:
				true if successful
		*/
		
		function changeDirectory(string $path): bool {
			return $this->Connection->chdir($path);
		}
		
		/*
			Function: connect
				Connects to a server.

			Parameters:
				host - The hostname of the server
				port - The port to connect to (defaults to 22)

			Returns:
				true if successful
		*/
		
		function connect(string $host, int $port = 22): bool {
			// Test connection
			$connection = @fsockopen($host, $port);
			
			if (is_resource($connection)) {
				fclose($connection);
				$this->Connection = new \Net_SFTP($host, $port);
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: createDirectory
				Creates a directory.

			Parameters:
				path - Full directory path to create or a path relative to the current directory.

			Returns:
				true if successful.
		*/
		
		function createDirectory(string $path): bool {
			return $this->Connection->mkdir($path);
		}
		
		/*
			Function: deleteDirectory
				Deletes a given directory (it must be empty to be deleted).

			Parameters:
				path - The full directory path or relative to the current directory.

			Returns:
				true if successful
		*/
		
		function deleteDirectory(string $path): bool {
			return $this->Connection->rmdir($path);
		}
		
		/*
			Function: deleteFile
				Deletes a given directory (it must be empty to be deleted).

			Parameters:
				path - The full file path or path relative to the current directory.

			Returns:
				true if successful
		*/
		
		function deleteFile(string $path): bool {
			return $this->Connection->delete($path);
		}
		
		/*
			Function: disconnect
				Closes the FTP connection.
		*/
		
		function disconnect(): bool {
			return $this->Connection->_disconnect("");
		}
		
		/*
			Function: downloadFile
				Downloads a file from the FTP server.

			Parameters:
				remote - The full path to the file to download (or the path relative to the current directory).
				local - The local path to store the downloaded file.

			Returns:
				true if successful.
		*/
		
		function downloadFile(string $remote, string $local): bool {
			return $this->Connection->get($remote, $local);
		}
		
		/*
			Function: getCurrentDirectory
				Returns the name of the current working directory.

			Returns:
				The current working directory or false if the call failed.
		*/
		
		function getCurrentDirectory(): string {
			return $this->Connection->pwd();
		}
		
		/*
			Function: getDirectoryContents
				Returns parsed directory information.

			Parameters:
				path - Optional directory to search, otherwises uses the current directory.

			Returns:
				An array of parsed information.
		*/
		
		function getDirectoryContents(string $path = ""): array {
			$types = ["1" => "f", "2" => "d", "3" => "l"];
			$list = $this->Connection->rawlist($path);
			$formatted_list = [];
			$names = [];
			
			foreach ($list as $line) {
				if ($line["filename"] != "." && $line["filename"] != "..") {
					// Make this the same as the BigTree\FTP class
					$formatted_list[] = [
						"type" => $types[$line["type"]],
						"perms" => $line["permissions"],
						"owner" => $line["uid"],
						"group" => $line["gid"],
						"size" => $line["size"],
						"date" => date("M d Y", $line["mtime"]),
						"name" => $line["filename"]
					];
					$names[] = $line["filename"];
				}
			}
			
			// Sort alphabetically
			array_multisort($names, $formatted_list);
			
			return $formatted_list;
		}
		
		/*
			Function: getRawDirectoryContents
				Calls a direct LIST command to the FTP server and returns the results.

			Parameters:
				path - Optional directory to search, otherwises uses the current directory.

			Returns:
				An array of information from the FTP LIST command.
		*/
		
		function getRawDirectoryContents(string $path = ""): array {
			return $this->Connection->rawlist($path);
		}
		
		/*
			Function: getSystemType
				This is here for method compatibility with BigTree\FTP but doesn't do anything.
		*/
		
		function getSystemType(): array {
			return [];
		}
		
		/*
			Function: login
				Login to the SFTP host.

			Parameters:
				user - SFTP username.
				pass - SFTP password.

			Returns:
				true if successful
		*/
		
		function login(?string $user = null, ?string $pass = null): bool {
			if (!$this->Connection) {
				return false;
			}
			
			if (!$this->Connection->login($user, $pass)) {
				return false;
			}
			
			return true;
		}
		
		/*
			Function: rename
				Renames a file or directory.

			Parameters:
				from - The current file/directory name (either absolute path or relative to current directory).
				to - The new file/directory name (either absolute path or relative to current directory).

			Returns:
				true if successful
		*/
		
		function rename(string $from, string $to): bool {
			return $this->Connection->rename($from, $to);
		}
		
		/*
			Function: setTransferType
				This is here for compatibility with BigTree\FTP but doesn't do anything.
		*/
		
		function setTransferType(string $mode): bool {
			return true;
		}
		
		/*
			Function: uploadFile
				Uploads a file to the FTP server.

			Parameters:
				local - The full path to the file to upload.
				remote - The full path to store the file at (or relative path to the current directory).

			Returns:
				true if successful
		*/
		
		function uploadFile(string $local, string $remote): bool {
			if (!@file_exists($local)) {
				return false;
			}
			
			return $this->Connection->put($remote, $local, NET_SFTP_LOCAL_FILE);
		}
		
	}
