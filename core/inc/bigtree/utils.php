<?
	/*
		Class: BigTree
			A utilities class with many useful functions.
	
	*/
	
	class BigTree {
	
		/*
			Function: arrayToXML
				Turns a PHP array into an XML string.
			
			Parameters:
				array - The array to convert.
				tab - Current tab depth (for recursion).
			
			Returns:
				A string of XML.
		*/				
		
		static function arrayToXML($array,$tab = "") {
			$xml = "";
			foreach ($array as $key => $val) {
				if (is_array($val)) {
					$xml .= "$tab<$key>\n".self::arrayToXML($val,"$tab\t")."$tab</$key>\n";
				} else {
					if (strpos($val,">") === false && strpos($val,"<") === false && strpos($val,"&") === false) {
						$xml .= "$tab<$key>$val</$key>\n";
					} else {
						$xml .= "$tab<$key><![CDATA[$val]]></$key>\n";
					}
				}
			}
			return $xml;
		}
		
		/*
			Function: centerCrop
				Crop from the center of an image to create a new one.
			
			Parameters:
				file - The location of the image to crop.
				newfile - The location to save the new cropped image.
				cw - The crop width.
				ch - The crop height.
		*/
		
		static function centerCrop($file, $newfile, $cw, $ch) {
			list($w, $h) = getimagesize($file);
			
			// Find out what orientation we're cropping at.
			$v = $cw / $w;
			$nh = $h * $v;
			if ($nh < $ch) {
				// We're shrinking the height to the crop height and then chopping the left and right off.
				$v = $ch / $h;
				$nw = $w * $v;
				$x = ceil(($nw - $cw) / 2 * $w / $nw);
				$y = 0;
				self::createCrop($file,$newfile,$x,$y,$cw,$ch,($w - $x * 2),$h);
			} else {
				$y = ceil(($nh - $ch) / 2 * $h / $nh);
				$x = 0;
				self::createCrop($file,$newfile,$x,$y,$cw,$ch,$w,($h - $y * 2));
			}
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
		
		static function colorMesh($first_color,$second_color,$percentage) {
			$percentage = intval(str_replace("%","",$percentage));
			$first_color = ltrim($first_color,"#");
			$second_color = ltrim($second_color,"#");
		
			// Get the RGB values for the colors
			$fc_r = hexdec(substr($first_color,0,2));
			$fc_g = hexdec(substr($first_color,2,2));
			$fc_b = hexdec(substr($first_color,4,2));
		
			$sc_r = hexdec(substr($second_color,0,2));
			$sc_g = hexdec(substr($second_color,2,2));
			$sc_b = hexdec(substr($second_color,4,2));
		
			$r_diff = ceil(($sc_r - $fc_r) * $percentage / 100);
			$g_diff = ceil(($sc_g - $fc_g) * $percentage / 100);
			$b_diff = ceil(($sc_b - $fc_b) * $percentage / 100);
		
			$new_color = "#".str_pad(dechex($fc_r + $r_diff),2,"0",STR_PAD_LEFT).str_pad(dechex($fc_g + $g_diff),2,"0",STR_PAD_LEFT).str_pad(dechex($fc_b + $b_diff),2,"0",STR_PAD_LEFT);
		
			return $new_color;
		}
		
		/*
			Function: compareTables
				Compares two tables in a MySQL database and tells you the SQL needed to get Table A to Table B.
				You can pass in the columns ahead of time if these tables exist in separate databases.
			
			Parameters:
				table_a - The table to modify.
				table_b - The table to turn table_a into.
				table_a_columns - (optional) table_a's column information
				table_b_columns - (optional) table_b's column information
			
			Returns:
				An array of queries needed to transform table_a into table_b.
		*/
		
		static function compareTables($table_a,$table_b,$table_a_columns = false,$table_b_columns = false) {
			$table_a_columns = !empty($table_a_columns) ? $table_a_columns : sqlcolumns($table_a);
			$table_b_columns = !empty($table_b_columns) ? $table_b_columns : sqlcolumns($table_b);
			
			$queries = array();
			$last_key = "";
			foreach ($table_b_columns as $key => $column) {
				$mod = "";
				$action = "";
				// If this column doesn't exist in the Table A table, add it.
				if (!isset($table_a_columns[$key])) {
					$action = "ADD";
				} elseif ($table_a_columns[$key] !== $column) {
					$action = "MODIFY";
				}
				
				if ($action) {
					$mod = "ALTER TABLE `$table_a` $action COLUMN `$key` ".$column["type"];
					if ($column["size"]) {
						$mod .= "(".$column["size"].")";
					}
					if ($column["type_extras"]) {
						$mod .= " ".$column["type_extras"];
					}
					if ($column["null"] == "NO") {
						$mod .= " NOT NULL";
					} else {
						$mod .= " NULL";
					}
					if ($column["default"]) {
						$d = $column["default"];
						if ($d == "CURRENT_TIMESTAMP" || $d == "NULL") {
							$mod .= " DEFAULT $d";
						} else {
							$mod .= " DEFAULT '".mysql_real_escape_string($d)."'";
						}
					}
					if ($column["extra"]) {
						$mod .= " ".$column["extra"];
					}
					
					if ($last_key) {
						$mod .= " AFTER `$last_key`";
					} else {
						$mod .= " FIRST";
					}
					
					$queries[] = $mod;
				}
				
				$last_key = $key;
			}
			
			foreach ($table_a_columns as $key => $column) {
				// If this key no longer exists in the new table, we should delete it.
				if (!isset($table_b_columns[$key])) {
					$queries[] = "ALTER TABLE `$table_a` DROP COLUMN `$key`";
				}	
			}
			
			return $queries;
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
		
		static function copyFile($from,$to) {
			if (!self::isDirectoryWritable($to)) {
				return false;
			}
			if (!is_readable($from)) {
				return false;
			}
			$pathinfo = self::pathInfo($to);
			$file_name = $pathinfo["basename"];
			$directory = $pathinfo["dirname"];
			$dir_parts = explode("/",ltrim($directory,"/"));
			
			$dpath = "/";
			foreach ($dir_parts as $d) {
				$dpath .= $d;
				// We're using the silence operator here because in situations with open_basedir restrictions checking for things farther up the path will result in warnings.
				if (!@file_exists($dpath)) {
					@mkdir($dpath);
					@chmod($dpath,0777);
				}
				$dpath .= "/";
			}
			
			copy($from,$to);
			chmod($to,0777);
			return true;
		}
		
		/*
			Function: createCrop
				Creates a cropped image from a source image.
				Uses ImageMagick extension if available. Falls back to gd.
			
			Parameters:
				file - The location of the image to crop.
				newfile - The location to save the new cropped image.
				x - The starting x value of the crop.
				y - The starting y value of the crop.
				crop_width - The width to crop from the original image.
				crop_height - The height to crop from the original image.
				width - The resized width of the new image.
				height - The resized height of the new image.
				jpeg_quality - The quality to save (for GD) the new image at. Defaults to 90.
		*/
		
		static function createCrop($file,$newfile,$x,$y,$crop_width,$crop_height,$width,$height,$jpeg_quality = 90) {
			if (!class_exists("Imagick",false)) {
				list($w, $h, $type) = getimagesize($file);
				$image_p = imagecreatetruecolor($crop_width,$crop_height);
				if ($type == IMAGETYPE_JPEG) {
					$image = imagecreatefromjpeg($file);
				} elseif ($type == IMAGETYPE_GIF) {
					$image = imagecreatefromgif($file);
				} elseif ($type == IMAGETYPE_PNG) {
					$image = imagecreatefrompng($file);
				}
		
				imagealphablending($image, true);
				imagealphablending($image_p, false);
				imagesavealpha($image_p, true);
				imagecopyresampled($image_p, $image, 0, 0, $x, $y, $crop_width, $crop_height, $width, $height);
		
				if ($type == IMAGETYPE_JPEG) {
					imagejpeg($image_p,$newfile,$jpeg_quality);
				} elseif ($type == IMAGETYPE_GIF) {
					imagegif($image_p,$newfile);
				} elseif ($type == IMAGETYPE_PNG) {
					imagepng($image_p,$newfile);
				}
				chmod($newfile,0777);
		
				imagedestroy($image);
				imagedestroy($image_p);
			} else {
				$image = new Imagick($file);
				$image->cropImage($width,$height,$x,$y);
				$image->thumbNailImage($crop_width,$crop_height);
				$image->writeImage($newfile);
			}
			return $newfile;
		}
		
		/*
			Function: createThumbnail
				Creates a thumbnailed image from a source image.
				Uses ImageMagick extension if available. Falls back to gd.
			
			Parameters:
				file - The location of the image to crop.
				newfile - The location to save the new cropped image.
				maxwidth - The maximum width of the new image.
				maxheight - The maximum height of the new image.
				jpeg_quality - The quality to save (for GD) the new image at. Defaults to 90.
		*/
		
		static function createThumbnail($file,$newfile,$maxwidth,$maxheight,$jpeg_quality = 90) {
			list($w, $h, $type) = getimagesize($file);
			if ($w > $maxwidth && $maxwidth) {
				$perc = $maxwidth / $w;
				$nw = $maxwidth;
				$nh = round($h * $perc,0);
				if ($nh > $maxheight && $maxheight) {
					$perc = $maxheight / $nh;
					$nh = $maxheight;
					$nw = round($nw * $perc,0);
				}
			} elseif ($h > $maxheight && $maxheight) {
				$perc = $maxheight / $h;
				$nh = $maxheight;
				$nw = round($w * $perc,0);
				if ($nw > $maxwidth && $maxwidth) {
					$perc = $maxwidth / $nw;
					$nw = $maxwidth;
					$nh = round($nh * $perc,0);
				}
			} else {
				$nw = $w;
				$nh = $h;
			}
		
			if (!class_exists("Imagick",false)) {
				$image_p = imagecreatetruecolor($nw, $nh);
				if ($type == IMAGETYPE_JPEG) {
					$image = imagecreatefromjpeg($file);
				} elseif ($type == IMAGETYPE_GIF) {
					$image = imagecreatefromgif($file);
				} elseif ($type == IMAGETYPE_PNG) {
					$image = imagecreatefrompng($file);
				}
		
				imagealphablending($image, true);
				imagealphablending($image_p, false);
				imagesavealpha($image_p, true);
				imagecopyresampled($image_p, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
		
				if ($type == IMAGETYPE_JPEG) {
					imagejpeg($image_p,$newfile,$jpeg_quality);
				} elseif ($type == IMAGETYPE_GIF) {
					imagegif($image_p,$newfile);
				} elseif ($type == IMAGETYPE_PNG) {
					imagepng($image_p,$newfile);
				}
				imagedestroy($image);
				imagedestroy($image_p);
				chmod($newfile,0777);
				return $newfile;
			} else {
				$image = new Imagick($file);
				$image->thumbnailImage($nw,$nh);
				$image->writeImage($newfile);
				return $newfile;
			}
		}
		
		/*
			Function: cURL
				Posts to a given URL and returns the response.
				Wrapper for cURL.
			
			Parameters:
				url - The URL to retrieve / POST to.
				post - A key/value pair array of things to POST (optional).
				options - A key/value pair of extra cURL options (optional).
			
			Returns:
				The string response from the URL.
		*/
		
		static function cURL($url,$post = array(),$options = array()) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			if (count($post)) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			if (count($options)) {
				foreach ($options as $key => $opt) {
					curl_setopt($ch, $key, $opt);
				}
			}
			$output = curl_exec($ch);
			curl_close($ch);
			return $output;
		}
		
		/*
			Function: deleteDirectory
				Deletes a directory including everything in it.
			
			Parameters:
				dir - The directory to delete.
		*/
		
		static function deleteDirectory($dir) {
			// Make sure it has a trailing /
			$dir = rtrim($dir,"/")."/";
			$r = opendir($dir);
			while ($file = readdir($r)) {
				if ($file != "." && $file != "..") {
					if (is_dir($dir.$file)) {
						self::deleteDirectory($dir.$file);
					} else {
						unlink($dir.$file);
					}
				}
			}
			rmdir($dir);
		}

		/*
			Function: formatBytes
				Formats bytes into larger units to make them more readable.
			
			Parameters:
				size - The number of bytes.
			
			Returns:
				A string with the number of bytes in kilobytes, megabytes, or gigabytes.
		*/
		
		static function formatBytes($size) {
			$units = array(' B', ' KB', ' MB', ' GB', ' TB');
			for ($i = 0; $size >= 1024 && $i < 4; $i++) {
				$size /= 1024;
			}
			return round($size, 2).$units[$i];
		}

		/*
			Function: formatCSS3
				Replaces CSS3 delcarations with vendor appropriate ones to reduce CSS redundancy.
			
			Parameters:
				css - CSS string.
			
			Returns:
				A string of CSS with vendor prefixes.
		*/

		static function formatCSS3($css) {
			// Border Radius Top Left - border-radius-top-left: 0px
			$css = preg_replace_callback('/border-radius-top-left:([^\"]*);/iU',create_function('$data','
				$r = trim($data[1]);
				return "border-top-left-radius: $r; -webkit-border-top-left-radius: $r; -moz-border-radius-topleft: $r;";
			'),$css);
			
			// Border Radius Top Right - border-radius-top-right: 0px
			$css = preg_replace_callback('/border-radius-top-right:([^\"]*);/iU',create_function('$data','
				$r = trim($data[1]);
				return "border-top-right-radius: $r; -webkit-border-top-right-radius: $r; -moz-border-radius-topright: $r;";
			'),$css);
			
			// Border Radius Bottom Left - border-radius-bottom-left: 0px
			$css = preg_replace_callback('/border-radius-bottom-left:([^\"]*);/iU',create_function('$data','
				$r = trim($data[1]);
				return "border-bottom-left-radius: $r; -webkit-border-bottom-left-radius: $r; -moz-border-radius-bottomleft: $r;";
			'),$css);
			
			// Border Radius Bottom Right - border-radius-bottom-right: 0px
			$css = preg_replace_callback('/border-radius-bottom-right:([^\"]*);/iU',create_function('$data','
				$r = trim($data[1]);
				return "border-bottom-right-radius: $r; -webkit-border-bottom-right-radius: $r; -moz-border-radius-bottomright: $r;";
			'),$css);
			
			// Background Gradients - background-gradient: #top #bottom
			$css = preg_replace_callback('/background-gradient:([^\"]*);/iU',create_function('$data','
				$d = trim($data[1]);
				list($stop,$start) = explode(" ",$d);
				$start_rgb = "rgb(".hexdec(substr($start,1,2)).",".hexdec(substr($start,3,2)).",".hexdec(substr($start,5,2)).")";
				$stop_rgb = "rgb(".hexdec(substr($stop,1,2)).",".hexdec(substr($stop,3,2)).",".hexdec(substr($stop,5,2)).")";
				return "background-image: -webkit-gradient(linear,left top,left bottom, color-stop(0, $start_rgb), color-stop(1, $stop_rgb)); background-image: -moz-linear-gradient(center top, $start_rgb 0%, $stop_rgb 100%); filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=$start, endColorstr=$stop);-ms-filter: \"progid:DXImageTransform.Microsoft.gradient(startColorstr=$start, endColorstr=$stop)\"; zoom:1;";
			'),$css);
			
			// Border Radius - border-radius: 0px 0px 0px 0px
			$css = preg_replace_callback('/border-radius:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// Box Shadow - box-shadow: 0px 0px 5px #color
			$css = preg_replace_callback('/box-shadow:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// Column Count - column-count: number
			$css = preg_replace_callback('/column-count:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// Column Rule - column-rule: 1px solid color
			$css = preg_replace_callback('/column-rule:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// Column Gap - column-gap: number
			$css = preg_replace_callback('/column-gap:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// Transition - transition: definition
			$css = preg_replace_callback('/transition:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			// User Select - user-select: none | text | toggle | element | elements | all | inherit
			$css = preg_replace_callback('/user-select:([^\"]*);/iU', 'BigTree::formatVendorPrefixes', $css);
			
			return $css;
		}
		
		/*
			Function: formatVendorPrefixes
				A preg_replace function for transforming a standard CSS3 entry into a vendor prefixed string.
			
			Parameters:
				data - preg data
			
			Returns:
				Replaced string.
		*/
		
		static function formatVendorPrefixes($data) {
		    $p = explode(":", $data[0]);
		    $d = trim($data[1]);
		    
		    $return = $p[0] . ": $d; ";
		    $return .= "-webkit-".$p[0].": $d; ";
		    $return .= "-moz-".$p[0].": $d; ";
		    $return .= "-ms-".$p[0].": $d; ";
		    $return .= "-o-".$p[0].": $d; ";
		    
		    return $return;
		}
		
		/*
			Function: getAvailableFileName
				Gets a web safe available file name in a given directory.
			
			Parameters:
				directory - The destination directory.
				file - The desired file name.
			
			Returns:
				An available, web safe file name.
		*/
		
		static function getAvailableFileName($directory,$file) {
			global $cms;
		
			$parts = self::pathInfo($directory.$file);
			
			// Clean up the file name
			$clean_name = $cms->urlify($parts["filename"]);
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name,0,50);
			}
			$file = $clean_name.".".$parts["extension"];
			
			// Just find a good filename that isn't used now.
			$x = 2;
			while (file_exists($directory.$file)) {
				$file = $clean_name."-$x.".$parts["extension"];
				$x++;
			}
			return $file;
		}
		
		/*
			Function: getFieldSelectOptions
				Get the <select> options of all the fields in a table.
			
			Parameters:
				table - The table to draw the fields for.
				default - The currently selected value.
				sorting - Whether to duplicate fields into "ASC" and "DESC" versions.
		*/
		
		static function getFieldSelectOptions($table,$default = "",$sorting = false) {
			$cols = sqlcolumns($table);
			echo '<option></option>';
			foreach ($cols as $col) {
				if ($sorting) {
					if ($default == $col["name"]." ASC") {
						echo '<option selected="selected">'.$col["name"].' ASC</option>';
					} else {
						echo '<option>'.$col["name"].' ASC</option>';
					}
					
					if ($default == $col["name"]." DESC") {
						echo '<option selected="selected">'.$col["name"].' DESC</option>';
					} else {
						echo '<option>'.$col["name"].' DESC</option>';
					}
				} else {
					if ($default == $col["name"]) {
						echo '<option selected="selected">'.$col["name"].'</option>';
					} else {
						echo '<option>'.$col["name"].'</option>';
					}
				}
			}
		}
		
		/*
			Function: getTableSelectOptions
				Get the <select> options for all of tables in the database excluding bigtree_ prefixed tables.
			
			Parameters:
				default - The currentlly selected value.
		*/
		
		static function getTableSelectOptions($default = false) {
			$q = sqlquery("SHOW TABLES");
			while ($f = sqlfetch($q)) {
				$tname = $f["Tables_in_".$GLOBALS["config"]["db"]["name"]];
				if ($GLOBALS["config"]["show_all_tables_in_dropdowns"] || ((substr($tname,0,8) !== "bigtree_"))) {
					if ($default == $f["Tables_in_".$GLOBALS["config"]["db"]["name"]]) {
						echo '<option selected="selected">'.$f["Tables_in_".$GLOBALS["config"]["db"]["name"]].'</option>';
					} else {
						echo '<option>'.$f["Tables_in_".$GLOBALS["config"]["db"]["name"]].'</option>';
					}
				}
			}
		}
		
		/*
			Function: globalizeArray
				Globalizes all the keys of an array into global variables without compromising $_ variables.
				Runs an array of functions on values that aren't arrays.
			
			Parameters:
				array - An array with key/value pairs.
				non_array_functions - An array of functions to perform on values that aren't arrays.
			
			See Also:
				<globalizeGETVars>
				<globalizePOSTVars>
		*/
		
		static function globalizeArray($array,$non_array_functions = array()) {
			foreach ($array as $key => $val) {
				if (strpos($key,0,1) != "_") {
					global $$key;
					if (is_array($val)) {
						$$key = $val;
					} else {
						foreach ($non_array_functions as $func) {
							$val = $func($val);
						}
						$$key = $val;
					}
				}
			}
		}
		
		/*
			Function: globalizeGETVars
				Globalizes all the $_GET variables without compromising $_ variables.
				Runs an array of functions on values that aren't arrays.
			
			Parameters:
				non_array_functions - An array of functions to perform on values that aren't arrays.
			
			See Also:
				<globalizeArray>
				<globalizePOSTVars>
				
		*/
		
		static function globalizeGETVars($non_array_functions = array()) {
			foreach ($_GET as $key => $val) {
				if (strpos($key,0,1) != "_") {
					global $$key;
					if (is_array($val)) {
						$$key = $val;
					} else {
						foreach ($non_array_functions as $func) {
							$val = $func($val);
						}
						$$key = $val;
					}
				}
			}
		}
		
		/*
			Function: globalizePOSTVars
				Globalizes all the $_POST variables without compromising $_ variables.
				Runs an array of functions on values that aren't arrays.
			
			Parameters:
				non_array_functions - An array of functions to perform on values that aren't arrays.
			
			See Also:
				<globalizeArray>
				<globalizeGETVars>
		*/
		
		static function globalizePOSTVars($non_array_functions = array()) {
			foreach ($_POST as $key => $val) {
				if (strpos($key,0,1) != "_") {
					global $$key;
					if (is_array($val)) {
						$$key = $val;
					} else {
						foreach ($non_array_functions as $func) {
							$val = $func($val);
						}
						$$key = $val;
					}
				}
			}
		}
		
		/*
			Function: isDirectoryWritable
				Extend's PHP's is_writable to support directories that don't exist yet.
			
			Parameters:
				path - The path to check the writable status of.
			
			Returns:
				true if the directory exists and is writable or could be created, otherwise false.
		*/
		static function isDirectoryWritable($path) {
			if (is_writable($path)) {
				return true;
			}
			$parts = explode("/",ltrim($path,"/"));
			unset($parts[count($parts)-1]);
			$path = "/".implode("/",$parts);
			if (!is_dir($path)) {
				return self::isDirectoryWritable($path);
			}
			return is_writable($path);
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
		
		static function moveFile($from,$to) {
			$success = self::copyFile($from,$to);
			if (!$success) {
				return false;
			}
			unlink($from);
			return true;
		}
		
		/*
			Function: path
				Get the proper path for a file based on whether a custom override exists.
			
			Parameters:
				file - File path relative to either core/ or custom/
			
			Returns:
				Hard file path to a custom/ (preferred) or core/ file depending on what exists.
		*/
		
		static function path($file) {
			global $server_root;
			if (file_exists($server_root."custom/".$file)) {
				return $server_root."custom/".$file;
			} else {
				return $server_root."core/".$file;
			}
		}
		
		/*
			Function: pathInfo
				Wrapper for PHP's pathinfo to make sure it supports returning "filename"
			
			Parameters:
				file - The full file path.
			
			Returns:
				Everything PHP's pathinfo() returns (with "filename" even when PHP doesn't suppor it).
			
			See Also:
				<http://php.net/manual/en/function.pathinfo.php>
		*/
		
		static function pathInfo($file) {
			$parts = pathinfo($file);
			if (!defined('PATHINFO_FILENAME')) {
				$parts["filename"] = substr($parts["basename"],0,strrpos($parts["basename"],'.'));
			}
			return $parts;
		}
		
		/*
			Function: prefixFile
				Prefixes a file name with a given prefix.
			
			Parameters:
				file - A file name or full file path.
				prefix - The prefix for the file name.
			
			Returns:
				The full path or file name with a prefix appended to the file name.
		*/
		
		static function prefixFile($file,$prefix) {
			$pinfo = self::pathInfo($file);
			return $pinfo["dirname"]."/".$prefix.$pinfo["basename"];
		}
		
		/*
			Function: randomString
				Returns a random string.
			
			Parameters:
				length - The number of characters to return.
				seeds - The seed set to use ("alpha" for lowercase letters, "numeric" for numbers, "alphanum" for uppercase letters and numbers, "hexidec" for hexidecimal)
			
			Returns:
				A random string.
		*/
		
		static function randomString($length = 8, $seeds = 'alphanum') {
			// Possible seeds
			$seedings['alpha'] = 'abcdefghijklmnopqrstuvwqyz';
			$seedings['numeric'] = '0123456789';
			$seedings['alphanum'] = 'ABCDEFGHJKLMNPQRTUVWXY0123456789';
			$seedings['hexidec'] = '0123456789abcdef';
		
			// Choose seed
			if (isset($seedings[$seeds])) {
				$seeds = $seedings[$seeds];
			}
		
			// Seed generator
			list($usec, $sec) = explode(' ', microtime());
			$seed = (float) $sec + ((float) $usec * 100000);
			mt_srand($seed);
		
			// Generate
			$str = '';
			$seeds_count = strlen($seeds);
			for ($i = 0; $length > $i; $i++) {
				$str .= $seeds { mt_rand(0, $seeds_count - 1) };
			}
			return $str;
		}
		
		/*
			Function: redirect
				Simple URL redirect via header with proper code #
			
			Parameters:
				url - The URL to redirect to.
				type - The type of redirect, defaults to normal 302 redirect.
		*/
		
		static function redirect($url = false, $type = "302") {
			if (!$url) {
				return false;
			} else if ($type == "301") {
				header ('HTTP/1.1 301 Moved Permanently');
			} else if ($type == "404") {
				header('HTTP/1.0 404 Not Found');
			}
			header("Location: ".$url);
			die();
		}
		
		/*
			Function: touchFile
				touch()s a file even if the directory for it doesn't exist yet.
			
			Parameters:
				file - The file path to touch.
		*/
		
		static function touchFile($file) {
			if (!self::isDirectoryWritable($file)) {
				return false;
			}
			$pathinfo = self::pathInfo($file);
			$file_name = $pathinfo["basename"];
			$directory = $pathinfo["dirname"];
			$dir_parts = explode("/",ltrim($directory,"/"));
		
			$dpath = "/";
			foreach ($dir_parts as $d) {
				$dpath .= $d;
				if (!file_exists($dpath)) {
					mkdir($dpath);
					chmod($dpath,0777);
				}
				$dpath .= "/";
			}
		
			touch($file);
			chmod($file,0777);
			return true;
		}
		
		/*
			Function: translateArray
				Steps through an array and creates internal page links for all parts of it.
				Requires $admin to be presently instantiated to BigTreeAdmin.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links encoded.
			
			See Also:
				<untranslateArray>
		*/
		
		static function translateArray($array) {
			global $admin;
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = self::translateArray($piece);
				} else {
					$piece = $admin->autoIPL($piece);
				}
			}
			return $array;
		}
		
		/*
			Function: trimLength
				A smarter version of trim that works with HTML.
			
			Parameters:
				string - A string of text or HTML.
				length - The number of characters to trim to.
			
			Returns:
				A string trimmed to the proper number of characters.
		*/
		
		static function trimLength($string,$length) {
			$ns = "";
			$opentags = array();
			$string = trim($string);
			if (strlen(html_entity_decode(strip_tags($string))) < $length) {
				return $string;
			}
			if (strpos($string," ") === false && strlen(html_entity_decode(strip_tags($string))) > $length) {
				return substr($string,0,$length)."...";
			}
			$x = 0;
			$z = 0;
			while ($z < $length && $x <= strlen($string)) {
				$char = substr($string,$x,1);
				$ns .= $char;		// Add the character to the new string.
				if ($char == "<") {
					// Get the full tag -- but compensate for bad html to prevent endless loops.
					$tag = "";
					while ($char != ">"	 && $char !== false) {
						$x++;
						$char = substr($string,$x,1);
						$tag .= $char;
					}
					$ns .= $tag;
		
					$tagexp = explode(" ",trim($tag));
					$tagname = str_replace(">","",$tagexp[0]);
		
					// If it's a self contained <br /> tag or similar, don't add it to open tags.
					if ($tagexp[1] != "/") {
		
						// See if we're opening or closing a tag.
						if (substr($tagname,0,1) == "/") {
							$tagname = str_replace("/","",$tagname);
							// We're closing the tag. Kill the most recently opened aspect of the tag.
							$y = sizeof($opentags);
							$done = false;
							reset($opentags);
							while (current($opentags) && !$done) {
								if (current($opentags) == $tagname) {
									unset($opentags[key($opentags)]);
									$done = true;
								}
								next($opentags);
							}
						} else {
							// Open a new tag.
							$opentags[] = $tagname;
						}
					}
				} elseif ($char == "&") {
					$entity = "";
					while ($char != ";" && $char != " " && $char != "<") {
						$x++;
						$char = substr($string,$x,1);
						$entity .= $char;
					}
					if ($char == ";") {
						$z++;
						$ns .= $entity;
					} elseif ($char == " ") {
						$z += strlen($entity);
						$ns .= $entity;
					} else {
						$z += strlen($entity);
						$ns .= substr($entity,0,-1);
						$x -= 2;
					}
				} else {
					$z++;
				}
				$x++;
			}
			while ($x < strlen($string) && !in_array(substr($string,$x,1),array(" ","!",".",",","<","&"))) {
				$ns .= substr($string,$x,1);
				$x++;
			}
			$ns.= "...";
			$opentags = array_reverse($opentags);
			foreach ($opentags as $key => $val) {
				$ns .= "</".$val.">";
			}
			return $ns;
		}
		
		/*
			Function: unformatBytes
				Formats a string of kilobytes / megabytes / gigabytes back into bytes.
			
			Parameters:
				size - The string of (kilo/mega/giga)bytes.
			
			Returns:
				The number of bytes.
		*/
		
		static function unformatBytes($size) {
			$type = substr($size,-1,1);
			$num = substr($size,0,-1);
			if ($type == "M") {
				return $num * 1048576;
			} elseif ($type == "K") {
				return $num * 1024;
			} elseif ($type == "G") {
				return ($num * 1024 * 1024 * 1024);
			}
			return 0;
		}
		
		/*
			Function: untranslateArray
				Steps through an array and creates hard links for all internal page links.
			
			Parameters:
				array - The array to process.
			
			Returns:
				An array with internal page links decoded.
			
			See Also:
				<translateArray>
		*/
		
		static function untranslateArray($array) {
			global $cms;
			foreach ($array as &$piece) {
				if (is_array($piece)) {
					$piece = self::untranslateArray($piece);
				} else {
					$piece = $cms->replaceInternalPageLinks($piece);
				}
			}
			return $array;
		}
		
		/*
			Function: uploadMaxFileSize
				Returns Apache's max file size value for use in forms.
		
			Returns:
				The integer value for setting a form's MAX_FILE_SIZE.
		*/
		
		static function uploadMaxFileSize() {
			$upload_max_filesize = ini_get("upload_max_filesize");
			if (!is_integer($upload_max_filesize)) {
				$upload_max_filesize = self::unformatBytes($upload_max_filesize);
			}
			
			$post_max_size = ini_get("post_max_size");
			if (!is_integer($post_max_size)) {
				$post_max_size = self::unformatBytes($post_max_size);
			}
			
			if ($post_max_size < $upload_max_filesize) {
				$upload_max_filesize = $post_max_size;
			}
			
			return $upload_max_filesize;
		}
		
	}

	// For servers that don't have multibyte string extensions…
	if (!function_exists("mb_strlen")) {
		function mb_strlen($string) { return strlen($string); }
	}
	if (!function_exists("mb_strtolower")) {
		function mb_strtolower($string) { return strtolower($string); }
	}

	$state_list = array('AL'=>"Alabama",'AK'=>"Alaska",'AZ'=>"Arizona",'AR'=>"Arkansas",'CA'=>"California",'CO'=>"Colorado",'CT'=>"Connecticut",'DE'=>"Delaware",'DC'=>"District Of Columbia", 'FL'=>"Florida",'GA'=>"Georgia",'HI'=>"Hawaii",'ID'=>"Idaho",'IL'=>"Illinois",'IN'=>"Indiana",'IA'=>"Iowa",'KS'=>"Kansas",'KY'=>"Kentucky",'LA'=>"Louisiana",'ME'=>"Maine",'MD'=>"Maryland",'MA'=>"Massachusetts",'MI'=>"Michigan",'MN'=>"Minnesota",'MS'=>"Mississippi",'MO'=>"Missouri",'MT'=>"Montana",'NE'=>"Nebraska",'NV'=>"Nevada",'NH'=>"New Hampshire",'NJ'=>"New Jersey",'NM'=>"New Mexico",'NY'=>"New York",'NC'=>"North Carolina",'ND'=>"North Dakota",'OH'=>"Ohio",'OK'=>"Oklahoma",'OR'=>"Oregon",'PA'=>"Pennsylvania",'RI'=>"Rhode Island",'SC'=>"South Carolina",'SD'=>"South Dakota",'TN'=>"Tennessee",'TX'=>"Texas",'UT'=>"Utah",'VT'=>"Vermont",'VA'=>"Virginia",'WA'=>"Washington",'WV'=>"West Virginia",'WI'=>"Wisconsin",'WY'=>"Wyoming");

	$country_list = array("United States","Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Central African Republic","Chad","Chile","China","Colombi","Comoros","Congo (Brazzaville)","Congo","Costa Rica","Cote d'Ivoire","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","East Timor (Timor Timur)","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Ethiopia","Fiji","Finland","France","Gabon","Gambia, The","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Korea, North","Korea, South","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Macedonia","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepa","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","Norway","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia and Montenegro","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","Spain","Sri Lanka","Sudan","Suriname","Swaziland","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe");

	$month_list = array("1" => "January","2" => "February","3" => "March","4" => "April","5" => "May","6" => "June","7" => "July","8" => "August","9" => "September","10" => "October","11" => "November","12" => "December");
?>