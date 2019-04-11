<?php
	/*
		Class: BigTree\FileSystem
			Provides an interface for interacting with the local file system.
	*/
	
	namespace BigTree;
	
	use Exception;
	
	class FileSystem
	{
		
		public static $OwnerResult = false;
		
		/*
			Function: copyDirectory
				Copies a directory and sets writable permissions.

			Parameters:
				source - The location of the directory to copy.
				destination - The new folder location.

			Returns:
				true if the copy was successful
		*/
		
		public static function copyDirectory(string $source, string $destination): bool
		{
			if (!static::getDirectoryWritability($destination) ||
				!is_dir($source) ||
				!is_readable($source) ||
				file_exists($destination)
			) {
				return false;
			}
			
			$source = rtrim(rtrim($source, "/"), "\\")."/";
			$contents = static::getDirectoryContents($source);
			$destination = rtrim(rtrim($destination, "/"), "\\")."/";
			
			mkdir($destination);
			static::setPermissions($destination);
			
			foreach ($contents as $file) {
				$new_location = $destination.str_replace($source, "", $file);
				
				if (is_dir($file)) {
					mkdir($new_location);
					static::setPermissions($new_location);
				} else {
					static::copyFile($file, $destination.str_replace($source, "", $file));
				}
			}
			
			return true;
		}
		
		/*
			Function: copyFile
				Copies a file into a directory, even if that directory doesn't exist yet.

			Parameters:
				from - The current location of the file.
				to - The location of the new copy.

			Returns:
				true if the copy was successful, false if the directories were not writable.
		*/
		
		public static function copyFile(string $from, string $to): bool
		{
			if (!static::getDirectoryWritability($to)) {
				return false;
			}
			
			// If the origin is a protocol agnostic URL, add http:
			if (substr($from, 0, 2) == "//") {
				$from = "http:".$from;
			}
			
			// is_readable doesn't work on URLs
			if (substr($from, 0, 7) != "http://" && substr($from, 0, 8) != "https://" && !is_readable($from)) {
				return false;
			}
			
			$pathinfo = pathinfo($to);
			$directory = $pathinfo["dirname"];
			static::createDirectory($directory);
			
			$success = copy($from, $to);
			static::setPermissions($to);
			
			return $success;
		}
		
		/*
			Function: createDirectory
				Makes a directory (and all applicable parent directories).
				Sets permissions to 777 if running as apache.

			Parameters:
				directory - The full path to the directory to be made.

			Returns:
				true if successful
		*/
		
		public static function createDirectory(string $directory): bool
		{
			// Make sure we skip open_basedir issues
			if (!static::getDirectoryWritability($directory)) {
				return false;
			}
			
			// Already exists, just say we made it
			if (file_exists($directory)) {
				return true;
			}
			
			// Windows systems aren't going to start with /
			if (substr($directory, 0, 1) == "/") {
				$directory_path = "/";
			} else {
				$directory_path = "";
			}
			
			$directory_parts = explode("/", trim($directory, "/"));
			
			foreach ($directory_parts as $part) {
				$directory_path .= $part;
				
				// Silence situations with open_basedir restrictions.
				if (!@file_exists($directory_path)) {
					@mkdir($directory_path);
					@static::setPermissions($directory_path);
				}
				
				$directory_path .= "/";
			}
			
			return true;
		}
		
		/*
			Function: createFile
				Writes data to a file, even if that directory for the file doesn't exist yet.
				Sets the file permissions to 777 if the file did not already exist and we're running as apache.

			Parameters:
				file - The location of the file.
				contents - The data to write.

			Returns:
				true if the creation was successful, false if the directories were not writable.
		*/
		
		public static function createFile(string $file, string $contents): bool
		{
			if (!static::getDirectoryWritability($file)) {
				return false;
			}
			
			$pathinfo = pathinfo($file);
			$directory = $pathinfo["dirname"];
			static::createDirectory($directory);
			
			if (!file_exists($file)) {
				file_put_contents($file, $contents);
				static::setPermissions($file);
			} else {
				file_put_contents($file, $contents);
			}
			
			return true;
		}
		
		/*
			Function: deleteDirectory
				Deletes a directory including everything in it.

			Parameters:
				directory - The directory to delete.

			Returns:
				true if successful
		*/
		
		public static function deleteDirectory(string $directory): bool
		{
			if (!file_exists($directory)) {
				return false;
			}
			
			// Make sure it has a trailing /
			$directory = rtrim($directory, "/")."/";
			
			$directory_handle = opendir($directory);
			while ($file = readdir($directory_handle)) {
				if ($file != "." && $file != "..") {
					if (is_dir($directory.$file)) {
						static::deleteDirectory($directory.$file);
					} else {
						unlink($directory.$file);
					}
				}
			}
			
			return rmdir($directory);
		}
		
		/*
			Function: deleteFile
				Deletes a file if it exists.

			Parameters:
				file - The file to delete

			Returns:
				true if successful
		*/
		
		public static function deleteFile(string $file): bool
		{
			if (file_exists($file)) {
				return unlink($file);
			}
			
			return false;
		}
		
		/*
			Function: getAvailableFileName
				Gets a web safe available file name in a given directory.

			Parameters:
				directory - The destination directory.
				file - The desired file name.
				prefixes - A list of file prefixes that also need to be accounted for when checking file name availability.

			Returns:
				An available, web safe file name.
		*/
		
		public static function getAvailableFileName(string $directory, string $file, array $prefixes = []): string
		{
			$parts = pathinfo($directory.$file);
			
			// Clean up the file name
			$clean_name = Link::urlify($parts["filename"]);
			
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name, 0, 50);
			}
			
			$file = $clean_name.".".strtolower($parts["extension"]);
			
			// Just find a good filename that isn't used now.
			$x = 2;
			
			while ($file === false || file_exists($directory.$file)) {
				$file = $clean_name."-$x.".strtolower($parts["extension"]);
				
				// Check prefixes
				foreach ($prefixes as $prefix) {
					if (file_exists($directory.$prefix.$file)) {
						$file = false;
					}
				}
				
				$x++;
			}
			
			return $file;
		}
		
		/*
			Function: getDirectoryContents
				Returns a directory's files and subdirectories (with their files) in a flat array with file paths.

			Parameters:
				directory - The directory to search
				recursive - Set to false to not recurse subdirectories (defaults to true).
				extension - Limit the results to a specific file extension (defaults to false).
				include_git - .git and .gitignore will be ignored unless set to true (defaults to false).

			Returns:
				An array of files/folder paths.
				Returns null if the directory cannot be read.
		*/
		
		public static function getDirectoryContents(string $directory, bool $recurse = true, ?string $extension = null,
											 bool $include_git = false): ?array
		{
			$contents = [];
			
			$directory_handle = @opendir($directory);
			
			if (!$directory_handle) {
				trigger_error('BigTree\FileSystem::getDirectoryContents failed to open directory: '.$directory, E_USER_WARNING);
				
				return null;
			}
			
			while ($file = readdir($directory_handle)) {
				if ($file != "." && $file != ".." && $file != ".DS_Store" && $file != "__MACOSX") {
					if ($include_git || ($file != ".git" && $file != ".gitignore")) {
						$path = rtrim($directory, "/")."/".$file;
						
						if (is_null($extension) || substr($path, -1 * strlen($extension)) == $extension) {
							$contents[] = $path;
						}
						
						if (is_dir($path) && $recurse) {
							$contents = array_merge($contents, static::getDirectoryContents($path, $recurse, $extension, $include_git));
						}
					}
				}
			}
			
			return $contents;
		}
		
		/*
			Function: getDirectoryWritability
				Extend's PHP's is_writable to support directories that don't exist yet.

			Parameters:
				path - The path to check the writable status of.

			Returns:
				true if the directory exists and is writable or could be created, otherwise false.
		*/
		
		public static function getDirectoryWritability(string $path, bool $recursion = false): bool
		{
			// We need to setup an error handler to catch open_basedir restrictions
			if (!$recursion) {
				set_error_handler(function ($error_number, $error_string) {
					if ($error_number == 2 && strpos($error_string, "open_basedir") !== false) {
						throw new Exception("open_basedir restriction in effect");
					}
				});
			}
			
			// If open_basedir restriction is hit we'll failover into the exceptiond and return false
			try {
				// Windows improperly returns writable status based on read-only flag instead of ACLs so we need our own version for Windows
				if (isset($_SERVER["OS"]) && stripos($_SERVER["OS"], "windows") !== false) {
					// Directory exists, check to see if we can create a temporary file inside it
					if (is_dir($path)) {
						$file = rtrim($path, "/")."/".uniqid().".tmp";
						$success = @touch($file);
						
						if ($success) {
							unlink($file);
							restore_error_handler();
							
							return true;
						}
						
						restore_error_handler();
						
						return false;
						
						// Remove the last directory from the path and then run isDirectoryWritable again
					} else {
						$parts = explode("/", $path);
						array_pop($parts);
						
						if (count($parts)) {
							return static::getDirectoryWritability(implode("/", $parts), true);
						}
						
						restore_error_handler();
						
						return false;
					}
				} else {
					// Directory exists, return its writable state
					if (is_dir($path)) {
						restore_error_handler();
						
						return is_writable($path);
					}
					
					// Remove the last directory from the path and try again
					$parts = explode("/", $path);
					array_pop($parts);
					
					return static::getDirectoryWritability(implode("/", $parts), true);
				}
			} catch (Exception $e) {
				restore_error_handler();
				
				return false;
			}
		}
		
		/*
			Function: getPrefixedFile
				Prefixes a file name with a given prefix.

			Parameters:
				file - A file name or full file path.
				prefix - The prefix for the file name.

			Returns:
				The full path or file name with a prefix appended to the file name.
		*/
		
		public static function getPrefixedFile(string $file, string $prefix): string
		{
			$path_info = pathinfo($file);
			$path_info["dirname"] = isset($path_info["dirname"]) ? $path_info["dirname"] : "";
			
			return $path_info["dirname"]."/".$prefix.$path_info["basename"];
		}
		
		/*
			Function: getRunningAsOwner
				Checks if the current script is running as the owner of the script.
				Useful for determining whether you need to 777 a file you're creating.

			Returns:
				true if PHP is running as the user that owns the file
		*/
		
		public static function getRunningAsOwner(): bool
		{
			// Already ran the test
			if (!is_null(static::$OwnerResult)) {
				return static::$OwnerResult;
			}
			
			// Only works on systems that support posix_getuid
			if (function_exists("posix_getuid")) {
				if (posix_getuid() == getmyuid()) {
					static::$OwnerResult = true;
				} else {
					static::$OwnerResult = false;
				}
			} else {
				static::$OwnerResult = false;
			}
			
			return static::$OwnerResult;
		}
		
		/*
			Function: getSafePath
				Makes sure that a file path doesn't contain abusive characters (i.e. ../)

			Parameters:
				file - A file name

			Returns:
				Cleaned up string.
		*/
		
		public static function getSafePath(string $file): string
		{
			$pieces = array_filter(explode("/", $file), function ($val) {
				// Let empties through
				if (!trim($val)) {
					return true;
				}
				
				// Strip path manipulation
				if (trim(str_replace(".", "", $val)) === "") {
					return false;
				}
				
				return true;
			});
			
			return implode("/", $pieces);
		}
		
		/*
			Function: moveFile
				Moves a file into a directory, even if that directory doesn't exist yet.

			Parameters:
				from - The current location of the file.
				to - The location of the new copy.

			Returns:
				true if the move was successful, false if the directories were not writable.
		*/
		
		public static function moveFile(string $from, string $to): bool
		{
			$success = static::copyFile($from, $to);
			
			if (!$success) {
				return false;
			}
			
			unlink($from);
			
			return true;
		}
		
		/*
			Function: setDirectoryPermissions
				Sets writable permissions for a whole directory.
				If the web server is not running as the owner of the current script, permissions will be 777.

			Parameters:
				location - The directory to set permissions on.
			
			Returns:
				true if successful
		*/
		
		public static function setDirectoryPermissions(string $location): bool
		{
			$contents = static::getDirectoryContents($location);
			$success = true;
			
			foreach ($contents as $file) {
				$file_success = static::setPermissions($file);
				
				if (!$file_success) {
					$success = false;
				}
			}
			
			return $success;
		}
		
		/*
			Function: setPermissions
				Checks to see if the current user the web server is running as is the owner of the current script.
				If they are not the same user, the file/directory is given a 777 permission so that the script owner can still manage the file.

			Parameters:
				location - The file or directory to set permissions on.

			Returns:
				true if successful
		*/
		
		public static function setPermissions(string $location): bool
		{
			if (!static::getRunningAsOwner()) {
				try {
					chmod($location, 0777);
				} catch (Exception $e) {
					return false;
				}
			}
			
			return true;
		}
		
		/*
			Function: touchFile
				touch()s a file even if the directory for it doesn't exist yet.

			Parameters:
				file - The file path to touch.
		*/
		
		public static function touchFile(string $file): bool
		{
			if (!static::getDirectoryWritability($file)) {
				return false;
			}
			
			$pathinfo = pathinfo($file);
			static::createDirectory($pathinfo["dirname"]);
			
			touch($file);
			
			if (file_exists($file) && static::setPermissions($file)) {
				return true;
			}
			
			return false;
		}
		
	}
	