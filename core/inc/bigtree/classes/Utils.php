<?php
	/*
	    Class: Utils
			A class of helper functions that just don't fit anywhere else.
	*/
	
	namespace BigTree;
	
	class Utils {
		
		/*
			Function: arrayToXML
				Turns a PHP array into an XML string.
			
			Parameters:
				array - The array to convert.
				tab - Current tab depth (for recursion).
			
			Returns:
				A string of XML.
		*/
		
		static function arrayToXML(array $array, string $tab = ""): string {
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
		
		static function arrayValue($value): array {
			if (is_string($value)) {
				$value = (array) @json_decode($value, true);
			}
			
			return $value;
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
		
		static function colorMesh(string $first_color, string $second_color, float $percentage): string {
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
		
		static function growl(string $title, string $message, string $type = "success"): void {
			$_SESSION["bigtree_admin"]["growl"] = ["message" => Text::translate($message), "title" => Text::translate($title), "type" => $type];
		}
		
		/*
			Function: ungrowl
				Deletes all pending growl messages.
		*/
		
		static function ungrowl(): void {
			unset($_SESSION["bigtree_admin"]["growl"]);
		}
		
	}
