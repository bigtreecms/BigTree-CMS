<?php
	/*
	    Class: Utils
			A class of helper functions that just don't fit anywhere else.
	*/
	
	namespace BigTree;
	
	class Utils {
		
		static $Hooks = null;
		
		/*
			Function: arrayToXML
				Turns a PHP array into an XML string.
			
			Parameters:
				array - The array to convert.
				tab - Current tab depth (for recursion).
			
			Returns:
				A string of XML.
		*/
		
		public static function arrayToXML(array $array, string $tab = ""): string {
			$xml = "";
			
			foreach ($array as $key => $val) {
				if (is_array($val)) {
					$xml .= "$tab<$key>\n".static::arrayToXML($val, "$tab\t")."$tab</$key>\n";
				} else {
					if (strpos($val, ">") === false && strpos($val, "<") === false && strpos($val, "&") === false) {
						$xml .= "$tab<$key>$val</$key>\n";
					} else {
						$xml .= "$tab<$key><![CDATA[$val]]></$key>\n";
					}
				}
			}
			
			// Remove trailing new line
			if ($tab === "") {
				$xml = trim($xml);
			}
			
			return $xml;
		}
		
		/*
			Function: arrayValue
				Checks to see if a value is a string. If it is it will be JSON decoded into an array.

			Parameters:
				value - A variable

			Returns:
				An array.
		*/
		
		public static function arrayValue($value): array {
			if (is_string($value)) {
				$value = (array) @json_decode($value, true);
			}
			
			return $value;
		}

		/*
			Function: cacheHooks
				Caches extension hooks.
		*/

		public static function cacheHooks() {
			$hooks = [];
			$extensions = DB::getAll("extensions");

			foreach ($extensions as $extension) {
				$base_dir = SERVER_ROOT."extensions/".$extension["id"]."/hooks/";

				if (file_exists($base_dir)) {
					$hook_files = FileSystem::getDirectoryContents($base_dir, true, "php");

					foreach ($hook_files as $file) {
						$parts = explode("/", str_replace($base_dir, "", substr($file, 0, -4)));

						if (count($parts) == 2) {
							$hooks[$parts[0]][$parts[1]][] = str_replace(SERVER_ROOT, "", $file);
						} elseif (count($parts) == 1) {
							$hooks[$parts[0]][] = str_replace(SERVER_ROOT, "", $file);
						}
					}
				}
			}

			FileSystem::createFile(SERVER_ROOT."cache/bigtree-hooks.json", JSON::encode($hooks));
		}
		
		/*
			Function: colorMesh
				Returns a color a % of the way between two colors.

			Parameters:
				first_color - The first color.
				second_color - The second color.
				percentage - The percentage between the first color and the second you want to move.

			Returns:
				A hex value color between the first and second colors.
		*/
		
		public static function colorMesh(string $first_color, string $second_color, float $percentage): string {
			$percentage = intval(str_replace("%", "", $percentage));
			$first_color = ltrim($first_color, "#");
			$second_color = ltrim($second_color, "#");
			
			// Get the RGB values for the colors
			$fc_r = hexdec(substr($first_color, 0, 2));
			$fc_g = hexdec(substr($first_color, 2, 2));
			$fc_b = hexdec(substr($first_color, 4, 2));
			
			$sc_r = hexdec(substr($second_color, 0, 2));
			$sc_g = hexdec(substr($second_color, 2, 2));
			$sc_b = hexdec(substr($second_color, 4, 2));
			
			$r_diff = ceil(($sc_r - $fc_r) * $percentage / 100);
			$g_diff = ceil(($sc_g - $fc_g) * $percentage / 100);
			$b_diff = ceil(($sc_b - $fc_b) * $percentage / 100);
			
			$new_color = "#".str_pad(dechex($fc_r + $r_diff), 2, "0", STR_PAD_LEFT).str_pad(dechex($fc_g + $g_diff), 2, "0", STR_PAD_LEFT).str_pad(dechex($fc_b + $b_diff), 2, "0", STR_PAD_LEFT);
			
			return strtoupper($new_color);
		}
		
		/*
			Function: growl
				Adds a growl message for the next admin page reload.

			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/
		
		public static function growl(string $title, string $message, string $type = "success"): void {
			$_SESSION["bigtree_admin"]["growl"] = ["message" => Text::translate($message), "title" => Text::translate($title), "type" => $type];
		}
		
		/*
		 	Function: keyById
				Keys an array by the IDs of the child arrays or objects
			
			Parameters:
				array - An array to key
		
			Returns:
				A modified array
		*/
		
		public static function keyById(array &$array): array {
			$keyed_array = [];
			
			foreach ($array as $item) {
				if (is_object($item)) {
					$keyed_array[$item->ID] = $item;
				} elseif (is_array($item)) {
					$keyed_array[$item["id"]] = $item;
				} else {
					trigger_error("A child element of the passed array was not an object or array.", E_USER_WARNING);
				}
			}
			
			return $keyed_array;
		}

		/*
			Function: runHooks
				Runs extension hooks of a given type for a given context.

			Parameters:
				type - Hook type
				context - Hook context
				data - Data to modify (will be returned modified)
				data_context - Additional data context (will be global variables in the context of the hook, not returned)

			Returns:
				Data modified by hook script
		*/

		public static function runHooks($type, $context = "", $data = "", $data_context = []) {
			if (!file_exists(SERVER_ROOT."cache/bigtree-hooks.json")) {
				static::cacheHooks();
			}

			if (is_null(static::$Hooks)) {
				static::$Hooks = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-hooks.json"), true);
			}

			// Anonymous function so that hooks can't pollute context
			$run_hook = function($hook, $data, $data_context = []) {
				foreach ($data_context as $key => $value) {
					$$key = $value;
				}

				include SERVER_ROOT.$hook;
				return $data;
			};

			if ($context) {
				if (!empty(static::$Hooks[$type][$context]) && is_array(static::$Hooks[$type][$context])) {
					foreach (static::$Hooks[$type][$context] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				} 
			} else {
				if (!empty(static::$Hooks[$type]) && is_array(static::$Hooks[$type])) {
					foreach (static::$Hooks[$type] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				} 
			}			

			return $data;
		}
		
		/*
			Function: ungrowl
				Deletes all pending growl messages.
		*/
		
		public static function ungrowl(): void {
			unset($_SESSION["bigtree_admin"]["growl"]);
		}
		
	}
