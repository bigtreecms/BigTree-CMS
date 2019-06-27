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
		public static $State = [];
		
		/*
			Function: drawState
				Draws the state array for reading by Vue into the app.
		
			Parameters:
				as_json - If true is passed, sends a full JSON response rather than a script tag.
		*/
		
		public static function drawState(bool $as_json = false): void
		{
			static::$State["main_nav"] = static::getMenuState();
			
			if (empty(static::$State["computed"])) {
				static::$State["computed"] = [];
			} else {
				$computed = static::$State["computed"];
				static::$State["computed"] = [];
			}
			
			if ($as_json) {
				header("Content-type: text/json");
				ob_clean();
				
				echo JSON::encode(static::$State);
			} else {
				echo '<script>const state = '.JSON::encode(static::$State).';</script>';
			}
		}
		
		public static function getMenuState(): array {
			$menu = [];
			
			foreach (Router::$AdminNavTree as $item) {
				if ($item["hidden"]) {
					continue;
				}
				
				if (empty($item["level"])) {
					$item["level"] = 0;
				}
				
				if (Auth::user()->Level >= $item["level"] && (!Auth::$PagesTabHidden || $item["link"] != "pages")) {
					// Need to check custom nav states better
					$link_pieces = explode("/", $item["link"]);
					$path_pieces = array_slice(Router::$Path, 1, count($link_pieces));
					
					if (strpos($item["link"], "https://") === 0 || strpos($item["link"], "http://") === 0) {
						$link = $item["link"];
					} else {
						$link = $item["link"] ? ADMIN_ROOT.$item["link"]."/" : ADMIN_ROOT;
					}
					
					$active = ($link_pieces == $path_pieces || ($item["link"] == "modules" && isset($bigtree["module"])));
					
					$menu_link = [
						"title" => $item["title"],
						"url" => $link,
						"icon" => $item["icon"],
						"active" => $active,
						"children" => []
					];
					
					if ($active && empty($item["no_top_level_children"]) && isset($item["children"]) && count($item["children"])) {
						foreach ($item["children"] as $child) {
							if (!empty($child["top_level_hidden"])) {
								continue;
							}
							
							if (strpos($child["link"], "https://") === 0 || strpos($child["link"], "http://") === 0) {
								$child_link = $child["link"];
							} else {
								$child_link = $child["link"] ? ADMIN_ROOT.rtrim($child["link"], "/")."/" : ADMIN_ROOT;
							}
							
							if (Auth::user()->Level >= $child["access"]) {
								$menu_link["children"][] = [
									"title" => $child["title"],
									"url" => $child_link
								];
							}
						}
					}
					
					$menu[] = $menu_link;
				}
			}
			
			return $menu;
		}
		
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
					$include_path = ADMIN_ROOT."$directory/$file";
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
		
		public static function registerRuntimeCSS(string $file): void
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
		
		public static function registerRuntimeJavascript(string $file): void
		{
			$include_path = static::getRuntimeIncludePath($file, "js");
			
			if (!in_array($include_path, static::$Javascript)) {
				static::$Javascript[] = $include_path;
			}
		}
		
		/*
			Function: setState
				Sets the state of the admin for passing in subnav, page title, meta, etc.
				
				Valid parameters:
					page_title - The page title / H1
					page_public_url - A link to the front end of the site next to the H1
					tools - Buttons that appear to the right of the H1
					meta_bar - An array of meta information shown above the page title
					breadcrumb - An array of title / url elements for the breadcrumb
					sub_nav - An array of sub-navigation
			
			Parameters:
				parameters - An array of state parameters.
		*/
		
		public static function setState(array $parameters): void
		{
			foreach ($parameters as $key => $value) {
				static::$State[$key] = $value;
			}
		}
		
	}
	