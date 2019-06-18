<?php
	/*
		Class: BigTree\Admin
			Provides a helper functions for interacting with the admin area.
	*/
	
	namespace BigTree;
	
	class Admin {
		
		public static $CSS = [];
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
		
		/*
			Function: registerRuntimeCSS
				Adds a CSS file to load at runtime.
			
			Parameters:
				file - CSS File (path relative to either /core/admin/css/ or /custom/admin/css/
		*/
		
		static function registerRuntimeCSS(string $file): void
		{
			static::$CSS[] = $file;
		}
		
		/*
			Function: registerRuntimeJavascript
				Adds a Javascript file to load at runtime.
			
			Parameters:
				file - Javascript File (path relative to either /core/admin/js/ or /custom/admin/js/
		*/
		
		static function registerRuntimeJavascript(string $file): void
		{
			static::$Javascript[] = $file;
		}
		
	}
	