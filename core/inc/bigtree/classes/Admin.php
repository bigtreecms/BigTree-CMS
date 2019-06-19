<?php
	/*
		Class: BigTree\Admin
			Provides a helper functions for interacting with the admin area.
	*/
	
	namespace BigTree;
	
	class Admin {
		
		public static $CSS = [];
		public static $CurrentModule = null;
		public static $Javascript = [];
		
		/*
			Function: growl
				Adds a growl message for the next admin page reload.

			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/
		
		public static function growl(string $title, string $message, string $type = "success"): void
		{
			$_SESSION["bigtree_admin"]["growl"] = [
				"message" => Text::translate($message),
				"title" => Text::translate($title),
				"type" => $type
			];
		}
		
		// Gets an include path for CSS / JS or another directory where it could be loading remote or in an extension
		protected static function getRuntimeIncludePath(string $file, string $directory): string
		{
			$path = explode("/", $file);
			
			if ($path[0] == "*") {
				// This is an extension piece acknowledging it could be used outside the extension root
				$include_path = ADMIN_ROOT.$file;
			} elseif (defined("EXTENSION_ROOT")) {
				// This is an extension inside its routed directory loading its own styles
				$include_path = ADMIN_ROOT."*/".static::$CurrentModule["extension"]."/$directory/$file";
			} else {
				if (substr($file, 0, 2) != "//" &&
					substr($file, 0, 7) != "http://" &&
					substr($file, 0, 8) != "https://")
				{
					// Local file include
					$include_path = Router::getIncludePath(ADMIN_ROOT."$directory/$file");
				} else {
					// Remote file include
					$include_path = $file;
				}
			}
			
			return $include_path;
		}
		
		/*
			Function: registerRuntimeCSS
				Adds a CSS file to load at runtime.
			
			Parameters:
				file - CSS File (path relative to either /core/admin/css/ or /custom/admin/css/
		*/
		
		static function registerRuntimeCSS(string $file): void
		{
			$include_path = static::getRuntimeIncludePath($file, "css");
			
			if (!in_array($include_path, static::$CSS)) {
				static::$CSS[] = $include_path;
			}
		}
		
		/*
			Function: registerRuntimeJavascript
				Adds a Javascript file to load at runtime.
			
			Parameters:
				file - Javascript File (path relative to either /core/admin/js/ or /custom/admin/js/
		*/
		
		static function registerRuntimeJavascript(string $file): void
		{
			$include_path = static::getRuntimeIncludePath($file, "js");
			
			if (!in_array($include_path, static::$Javascript)) {
				static::$Javascript[] = $include_path;
			}
		}
		
	}
	