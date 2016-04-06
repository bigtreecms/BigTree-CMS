<?php
	/*
		Class: BigTree\Image
			Provides an interface for handling BigTree images.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeStorage;

	class Image {

		/*
			Function: centerCrop
				Crop from the center of an image to create a new one.

			Parameters:
				file - The location of the image to crop.
				newfile - The location to save the new cropped image.
				crop_width - The crop width.
				crop_height - The crop height.
				retina - Whether to try to create a retina crop (2x, defaults false)
				grayscale - Whether to convert to grayscale (defaults false)

			Returns:
				The new file name if successful, false if there was not enough memory available.
		*/

		static function centerCrop($file, $newfile, $crop_width, $crop_height, $retina = false, $grayscale = false) {
			list($width, $height) = getimagesize($file);

			// Find out what orientation we're cropping at.
			$ratio = $crop_width / $width;
			$new_height = $height * $ratio;

			if ($new_height < $crop_height) {
				// We're shrinking the height to the crop height and then chopping the left and right off.
				$ratio = $crop_height / $height;
				$nw = $width * $ratio;
				$x = ceil(($nw - $crop_width) / 2 * $width / $nw);
				$y = 0;

				return static::createCrop($file,$newfile,$x,$y,$crop_width,$crop_height,($width - $x * 2),$height,$retina,$grayscale);
			} else {
				$y = ceil(($new_height - $crop_height) / 2 * $height / $new_height);
				$x = 0;

				return static::createCrop($file,$newfile,$x,$y,$crop_width,$crop_height,$width,($height - $y * 2),$retina,$grayscale);
			}
		}

		/*
			Function: convertPNGToJPEG
				Replaces (in place) a PNG file with a JPG equivalent.

			Parameters:
				file - Path to PNG file to convert
				name - Desired file name

			Returns:
				jpg file name version of the desired file name
		*/

		static function convertPNGToJPEG($file,$name) {
			global $bigtree;

			// Try to figure out what this file is
			list($image_width,$image_height,$image_type) = @getimagesize($file);
			
			if ($image_type !== IMAGETYPE_PNG) {
				return false;
			}
			
			// See if this PNG has any alpha channels, if it does we're not doing a JPG conversion.
			$alpha = ord(@file_get_contents($file,null,null,25,1));
			if ($alpha != 4 && $alpha != 6) {
				// Convert the PNG to JPG
				$source = imagecreatefrompng($file);
				imagejpeg($source,$file,$bigtree["config"]["image_quality"]);
				imagedestroy($source);

				// If they originally uploaded a JPG we converted into a PNG, we don't want to change the desired filename, but if they uploaded a PNG the new file should be JPG
				if (strtolower(substr($name,-3,3)) == "png") {
					$name = substr($name,0,-3)."jpg";
				}
			}

			return $name;
		}

		/*
			Function: createCrop
				Creates a cropped image from a source image.
			
			Parameters:
				file - The location of the image to crop.
				new_file - The location to save the new cropped image.
				x - The starting x value of the crop.
				y - The starting y value of the crop.
				target_width - The desired width of the new image.
				target_height - The desired height of the new image.
				width - The width to crop from the original image.
				height - The height to crop from the original image.
				retina - Whether to create a retina-style image (2x, lower quality) if able, defaults to false
				grayscale - Whether to make the crop be in grayscale or not, defaults to false

			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
		*/
		
		static function createCrop($file,$new_file,$x,$y,$target_width,$target_height,$width,$height,$retina = false,$grayscale = false) {
			global $bigtree;

			// If we don't have the memory available, fail gracefully.
			if (!static::getMemoryAvailability($file,$target_width,$target_height)) {
				return false;
			}
			
			$jpeg_quality = isset($bigtree["config"]["image_quality"]) ? $bigtree["config"]["image_quality"] : 90;
			
			// If we're doing a retina image we're going to check to see if the cropping area is at least twice the desired size
			if ($retina && ($x + $width) >= $target_width * 2 && ($y + $height) >= $target_height * 2) {
				$jpeg_quality = isset($bigtree["config"]["retina_image_quality"]) ? $bigtree["config"]["retina_image_quality"] : 25;
				$target_width *= 2;
				$target_height *= 2;
			}
			
			list($w, $h, $type) = getimagesize($file);
			$cropped_image = imagecreatetruecolor($target_width,$target_height);
			if ($type == IMAGETYPE_JPEG) {
				$original_image = imagecreatefromjpeg($file);
			} elseif ($type == IMAGETYPE_GIF) {
				$original_image = imagecreatefromgif($file);
			} elseif ($type == IMAGETYPE_PNG) {
				$original_image = imagecreatefrompng($file);
			} else {
				return false;
			}
			
			imagealphablending($original_image, true);
			imagealphablending($cropped_image, false);
			imagesavealpha($cropped_image, true);
			imagecopyresampled($cropped_image, $original_image, 0, 0, $x, $y, $target_width, $target_height, $width, $height);
			
			if ($grayscale) {
				imagefilter($cropped_image, IMG_FILTER_GRAYSCALE);
			}
		
			if ($type == IMAGETYPE_JPEG) {
				imagejpeg($cropped_image,$new_file,$jpeg_quality);
			} elseif ($type == IMAGETYPE_GIF) {
				imagegif($cropped_image,$new_file);
			} elseif ($type == IMAGETYPE_PNG) {
				imagepng($cropped_image,$new_file);
			}

			FileSystem::setPermissions($new_file);
		
			imagedestroy($original_image);
			imagedestroy($cropped_image);
			
			return $new_file;
		}

		/*
			Function: createThumbnail
				Creates a thumbnailed image from a source image.
			
			Parameters:
				file - The location of the image to crop.
				new_file - The location to save the new cropped image.
				maxwidth - The maximum width of the new image (0 for no max).
				maxheight - The maximum height of the new image (0 for no max).
				retina - Whether to create a retina-style image (2x, lower quality) if able (defaults to false).
				grayscale - Whether to make the crop be in grayscale or not (defaults to false).
				upscale - If set to true, upscales to the maxwidth / maxheight instead of downscaling (defaults to false, disables retina).

			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
			
			See Also:
				createUpscaledImage
		*/
		
		static function createThumbnail($file,$new_file,$max_width,$max_height,$retina = false,$grayscale = false,$upscale = false) {
			global $bigtree;
			
			$jpeg_quality = isset($bigtree["config"]["image_quality"]) ? $bigtree["config"]["image_quality"] : 90;
			
			if ($upscale) {
				list($type,$w,$h,$result_width,$result_height) = static::getUpscaleSizes($file,$max_width,$max_height);
			} else {
				list($type,$w,$h,$result_width,$result_height) = static::getThumbnailSizes($file,$max_width,$max_height);
			}
			
			// If we're doing retina, see if 2x the height/width is less than the original height/width and change the quality.
			if ($retina && !$upscale && $result_width * 2 <= $w && $result_height * 2 <= $h) {
				$jpeg_quality = isset($bigtree["config"]["retina_image_quality"]) ? $bigtree["config"]["retina_image_quality"] : 25;
				$result_width *= 2;
				$result_height *= 2;
			}

			// If we don't have the memory available, fail gracefully.
			if (!static::getMemoryAvailability($file,$result_width,$result_height)) {
				return false;
			}

			$thumbnailed_image = imagecreatetruecolor($result_width, $result_height);
			if ($type == IMAGETYPE_JPEG) {
				$original_image = imagecreatefromjpeg($file);
			} elseif ($type == IMAGETYPE_GIF) {
				$original_image = imagecreatefromgif($file);
			} elseif ($type == IMAGETYPE_PNG) {
				$original_image = imagecreatefrompng($file);
			} else {
				return false;
			}
		
			imagealphablending($original_image, true);
			imagealphablending($thumbnailed_image, false);
			imagesavealpha($thumbnailed_image, true);
			imagecopyresampled($thumbnailed_image, $original_image, 0, 0, 0, 0, $result_width, $result_height, $w, $h);
		
			if ($grayscale) {
				imagefilter($thumbnailed_image, IMG_FILTER_GRAYSCALE);
			}
		
			if ($type == IMAGETYPE_JPEG) {
				imagejpeg($thumbnailed_image,$new_file,$jpeg_quality);
			} elseif ($type == IMAGETYPE_GIF) {
				imagegif($thumbnailed_image,$new_file);
			} elseif ($type == IMAGETYPE_PNG) {
				imagepng($thumbnailed_image,$new_file);
			}

			FileSystem::setPermissions($new_file);
			
			imagedestroy($original_image);
			imagedestroy($thumbnailed_image);
			
			return $new_file;
		}

		/*
			Function: getMemoryAvailability
				Checks whether there is enough memory available to perform an image manipulation.

			Parameters:
				source - The source image file
				width - The width of the new image to be created
				height - The height of the new image to be created

			Returns:
				true if the image can be created, otherwise false.
		*/

		static function getMemoryAvailability($source,$width,$height) {
			$available_memory = intval(ini_get('memory_limit')) * 1024 * 1024;
			$info = getimagesize($source);
			$source_width = $info[0];
			$source_height = $info[1];

			// GD takes about 70% extra memory for JPG and we're most likely running 3 bytes per pixel
			if ($info["mime"] == "image/jpg" || $info["mime"] == "image/jpeg") {
				$source_size = ceil($source_width * $source_height * 3 * 1.7); 
				$target_size = ceil($width * $height * 3 * 1.7);
			// GD takes about 250% extra memory for GIFs which are most likely running 1 byte per pixel
			} elseif ($info["mime"] == "image/gif") {
				$source_size = ceil($source_width * $source_height * 2.5); 
				$target_size = ceil($width * $height * 2.5);
			// GD takes about 245% extra memory for PNGs which are most likely running 4 bytes per pixel
			} elseif ($info["mime"] == "image/png") {
				$source_size = ceil($source_width * $source_height * 4 * 2.45);
				$target_size = ceil($width * $height * 4 * 2.45);
			} else {
				return true;
			}

			$memory_usage = $source_size + $target_size + memory_get_usage();
			if ($memory_usage > $available_memory) {
				return false;
			}
			return true;
		}

		/*
			Function: getThumbnailSizes
				Returns a list of sizes of an image and the result sizes.
			
			Parameters:
				file - The location of the image to crop.
				maxwidth - The maximum width of the new image (0 for no max).
				maxheight - The maximum height of the new image (0 for no max).
			
			Returns:
				An array with (type,width,height,result width,result height)
		*/
		
		static function getThumbnailSizes($file,$max_width,$max_height) {
			list($w, $h, $type) = getimagesize($file);

			if ($w > $max_width && $max_width) {
				$perc = $max_width / $w;
				$result_width = $max_width;
				$result_height = round($h * $perc,0);
				if ($result_height > $max_height && $max_height) {
					$perc = $max_height / $result_height;
					$result_height = $max_height;
					$result_width = round($result_width * $perc,0);
				}
			} elseif ($h > $max_height && $max_height) {
				$perc = $max_height / $h;
				$result_height = $max_height;
				$result_width = round($w * $perc,0);
				if ($result_width > $max_width && $max_width) {
					$perc = $max_width / $result_width;
					$result_width = $max_width;
					$result_height = round($result_height * $perc,0);
				}
			} else {
				$result_width = $w;
				$result_height = $h;
			}
			
			return array($type,$w,$h,$result_width,$result_height);
		}

		/*
			Function: getUpscaleSizes
				Returns a list of sizes of an image and the result sizes.
			
			Parameters:
				file - The location of the image to crop.
				min_width - The minimum width of the new image (0 for no min).
				min_height - The maximum height of the new image (0 for no min).
			
			Returns:
				An array with (type,width,height,result width,result height)
		*/
		
		static function getUpscaleSizes($file,$min_width,$min_height) {
			list($w, $h, $type) = getimagesize($file);

			if ($w < $min_width && $min_width) {
				$perc = $min_width / $w;
				$result_width = $min_width;
				$result_height = round($h * $perc,0);
				if ($result_height < $min_height && $min_height) {
					$perc = $min_height / $result_height;
					$result_height = $min_height;
					$result_width = round($result_width * $perc,0);
				}
			} elseif ($h < $min_height && $min_height) {
				$perc = $min_height / $h;
				$result_height = $min_height;
				$result_width = round($w * $perc,0);
				if ($result_width < $min_width && $min_width) {
					$perc = $min_width / $result_width;
					$result_width = $min_width;
					$result_height = round($result_height * $perc,0);
				}
			} else {
				$result_width = $w;
				$result_height = $h;
			}
			
			return array($type,$w,$h,$result_width,$result_height);
		}

		/*
			Function: placeholder
				Generates placeholder image data and serves it.
				Ends script execution.

			Parameters:
				width - The width of desired image
				height - The height of desired image
				bg_color - The background color; must be full 6 charachter hex value
				text_color - The text color; must be full 6 charachter hex value
				icon_path - Image to render, disables text rendering, must be gif, jpeg, or png
				text_string - Text to render; overrides default dimension display

			Returns:
				Nothing; Renders a placeholder image
		*/

		static function placeholder($width, $height, $bg_color = false, $text_color = false, $icon_path = false, $text_string = false) {
			// Check size
			$width = ($width > 2000) ? 2000 : $width;
			$height = ($height > 2000) ? 2000 : $height;

			// Check colors
			$bg_color = (!$bg_color && $bg_color != "000" && $bg_color != "000000") ? "CCCCCC" : ltrim($bg_color,"#");
			$text_color = (!$text_color  && $text_color != "000" && $text_color != "000000") ? "666666" : ltrim($text_color,"#");

			// Set text
			$text = $text_string;
			if ($icon_path) {
				$text = "";
			} else {
				if (!$text_string) {
					$text = $width . " X " . $height;
				}
			}

			// Create image
			$image = imagecreatetruecolor($width, $height);
			// Build rgba from hex
			$bg_color = imagecolorallocate($image, base_convert(substr($bg_color, 0, 2), 16, 10), base_convert(substr($bg_color, 2, 2), 16, 10), base_convert(substr($bg_color, 4, 2), 16, 10));
			$text_color = imagecolorallocate($image, base_convert(substr($text_color, 0, 2), 16, 10), base_convert(substr($text_color, 2, 2), 16, 10), base_convert(substr($text_color, 4, 2), 16, 10));
			// Fill image
			imagefill($image, 0, 0, $bg_color);

			// Add icon if provided
			if ($icon_path) {
				$icon_size = getimagesize($icon_path);
				$icon_width = $icon_size[0];
				$icon_height = $icon_size[1];
				$icon_x = ($width - $icon_width) / 2;
				$icon_y = ($height - $icon_height) / 2;

				$ext = strtolower(substr($icon_path,-3));
				if ($ext == "jpg" || $ext == "peg") {
					$icon = imagecreatefromjpeg($icon_path);
				} elseif ($ext == "gif") {
					$icon = imagecreatefromgif($icon_path);
				} else {
					$icon = imagecreatefrompng($icon_path);
				}
				imagesavealpha($icon, true);
				imagealphablending($icon, true);
				imagecopyresampled($image, $icon, $icon_x, $icon_y, 0, 0, $icon_width, $icon_height, $icon_width, $icon_height);
				// Add text if provided or default to size
			} elseif ($text) {
				$font = Router::getIncludePath("inc/lib/fonts/arial.ttf");
				$fontsize = ($width > $height) ? ($height / 15) : ($width / 15);
				$textpos = imageTTFBbox($fontsize, 0, $font, $text);
				imagettftext($image, $fontsize, 0, (($width - $textpos[2]) / 2), (($height - $textpos[5]) / 2), $text_color, $font, $text);
			}

			// Serve image and die
			header("Content-Type: image/png");
			imagepng($image);
			imagedestroy($image);
			die();
		}

		/*
			Function: processCrops
				Processes a list of cropped images.
				Must be run in the context of a POST from the cropper.

			Parameters:
				crop_key - A cache key pointing to the location of crop data.
		*/

		static function processCrops($crop_key) {
			$storage = new BigTreeStorage;

			// Get and remove the crop data
			$crops = Cache::get("org.bigtreecms.crops",$crop_key);
			Cache::delete("org.bigtreecms.crops",$crop_key);

			foreach ($crops as $key => $crop) {
				$image_src = $crop["image"];
				$target_width = $crop["width"];
				$target_height = $crop["height"];
				$x = $_POST["x"][$key];
				$y = $_POST["y"][$key];
				$width = $_POST["width"][$key];
				$height = $_POST["height"][$key];
				$thumbs = $crop["thumbs"];
				$center_crops = $crop["center_crops"];

				$pinfo = pathinfo($image_src);

				// Create the crop and put it in a temporary location
				$temp_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
				static::createCrop($image_src,$temp_crop,$x,$y,$target_width,$target_height,$width,$height,$crop["retina"],$crop["grayscale"]);
				
				// Make thumbnails for the crop
				if (is_array($thumbs)) {
					foreach ($thumbs as $thumb) {
						if (is_array($thumb) && ($thumb["height"] || $thumb["width"])) {
							// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
							list($type,$w,$h,$result_width,$result_height) = static::getThumbnailSizes($temp_crop,$thumb["width"],$thumb["height"]);

							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							static::createCrop($image_src,$temp_thumb,$x,$y,$result_width,$result_height,$width,$height,$crop["retina"],$thumb["grayscale"]);
							$storage->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
						}
					}
				}

				// Make center crops of the crop
				if (is_array($center_crops)) {
					foreach ($center_crops as $center_crop) {
						if (is_array($center_crop) && $center_crop["height"] && $center_crop["width"]) {
							$temp_center_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							static::centerCrop($temp_crop,$temp_center_crop,$center_crop["width"],$center_crop["height"],$crop["retina"],$center_crop["grayscale"]);
							$storage->replace($temp_center_crop,$center_crop["prefix"].$crop["name"],$crop["directory"]);
						}
					}
				}

				// Move crop into its resting place
				$storage->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
			}

			// Remove all the temporary images
			foreach ($crops as $crop) {
				FileSystem::deleteFile($crop["image"]);
			}
		}

	}
