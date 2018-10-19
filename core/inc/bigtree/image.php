<?php
	/*
		Class: BigTreeImage
			Provides an interface for handling an image.
	*/
	
	class BigTreeImage {
		
		public $BitsPerPixel;
		public $ColorChannels = 3;
		public $Error = null;
		public $File;
		public $Height = 0;
		public $MimeType;
		public $MinHeight;
		public $MinWidth;
		public $Prefixes;
		public $Settings = [];
		public $Storage;
		public $StoredFile;
		public $StoredName;
		public $Type;
		public $Width = 0;
		public static $SavedMemoryLimit;
		
		private $Extensions = [IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif"];
		private $ForcingLocalReplace = false;
		private $IgnoringMinimums = false;
		
		public function __construct($file, $settings = [], $ignore_minimums = false) {
			global $bigtree;

			if (strpos($file, "//") === 0) {
				$file = "http:".$file;
			}

			$this->IgnoringMinimums = $ignore_minimums;
			$this->Settings = $settings;
			$this->cleanSettings();

			$this->Storage = new BigTreeStorage;
			$this->Storage->AutoJPEG = $bigtree["config"]["image_force_jpeg"];

			$info = getimagesize($file);
			$this->Type = $info[2];
			
			if ($this->Type != IMAGETYPE_JPEG && $this->Type != IMAGETYPE_GIF && $this->Type != IMAGETYPE_PNG) {
				$this->Error = "The file is an invalid image or is an unsupported image type.";
				
				return;
			}
			
			$this->Height = $info[1];
			$this->Width = $info[0];
			$this->MinHeight = (!empty($settings["min_height"]) && is_numeric($settings["min_height"])) ? intval($settings["min_height"]) : 0;
			$this->MinWidth = (!empty($settings["min_width"]) && is_numeric($settings["min_width"])) ? intval($settings["min_width"]) : 0;
			$this->fixRotation();

			if (!$ignore_minimums && ($this->Height < $this->MinHeight || $this->Width < $this->MinWidth)) {
				$error = "The image did not meet the minimum size of ";
				
				if ($this->MinHeight && $this->MinWidth) {
					$error .= $this->MinWidth."x".$this->MinHeight." pixels.";
				} elseif ($this->MinHeight) {
					$error .= $this->MinHeight." pixels tall.";
				} elseif ($this->MinWidth) {
					$error .= $this->MinWidth." pixels wide.";
				}

				$this->Error = $error;

				return;
			}

			$this->BitsPerPixel = $info["bits"] ?: 8;
			$this->MimeType = $info["mime"];
			
			if ($info["channels"]) {
				$this->ColorChannels = $info["channels"];
			} elseif ($this->Type == IMAGETYPE_JPEG) {
				$this->ColorChannels = 3;
			} elseif ($this->Type == IMAGETYPE_GIF) {
				$this->ColorChannels = 1;
			} elseif ($this->Type == IMAGETYPE_PNG) {
				$this->ColorChannels = 4;
			}

			if ($this->ColorChannels == 4 && $this->Type == IMAGETYPE_JPEG) {
				$this->Error = "A CMYK encoded file was uploaded. Please upload an RBG image.";

				return;
			}
			
			// If this was a user uploaded file, move it to a temporary directory for manipulation and out of /tmp
			if ((sys_get_temp_dir() && strpos($file, sys_get_temp_dir()) === 0) || 
				(ini_get("upload_tmp_dir") && strpos($file, ini_get("upload_tmp_dir")) === 0)) 
			{
				$temp = $this->getTempFileName();
				move_uploaded_file($file, $temp);
				$this->File = $temp;
			} else {
				$this->File = $file;
			}
		}
		
		/*
			Function: centerCrop
				Crop from the center of an image to create a new one.

			Parameters:
				location - The location to save the new cropped image (pass null to replace the source image).
				crop_width - The crop width.
				crop_height - The crop height.
				retina - Whether to try to create a retina crop (2x, defaults false)
				grayscale - Whether to convert to grayscale (defaults false)

			Returns:
				The new file name if successful, false if there was not enough memory available.
		*/
		
		public function centerCrop($location, $crop_width, $crop_height, $retina = false, $grayscale = false) {
			// Find out what orientation we're cropping at.
			$v = $crop_width / $this->Width;
			$new_height = $this->Height * $v;
			
			if ($new_height < $crop_height) {
				// We're shrinking the height to the crop height and then chopping the left and right off.
				$v = $crop_height / $this->Height;
				$new_width = $this->Width * $v;
				$x = ceil(($new_width - $crop_width) / 2 * $this->Width / $new_width);
				$y = 0;
				
				return $this->crop($location, $x, $y, $crop_width, $crop_height, ($this->Width - $x * 2), $this->Height, $retina, $grayscale);
			} else {
				$y = ceil(($new_height - $crop_height) / 2 * $this->Height / $new_height);
				$x = 0;
				
				return $this->crop($location, $x, $y, $crop_width, $crop_height, $this->Width, ($this->Height - $y * 2), $retina, $grayscale);
			}
		}
		
		/*
			Function: checkMemory
				Checks whether there is enough memory available to perform an image manipulation.

			Parameters:
				width - The width of the new image to be created
				height - The height of the new image to be created

			Returns:
				true if the image can be created, otherwise false.
		*/
		
		public function checkMemory($width, $height) {
			global $bigtree;
			
			$image_memory_limit = !empty($bigtree["config"]["image_memory_limit"]) ? $bigtree["config"]["image_memory_limit"] : "256M";
			$available_memory = intval($image_memory_limit) * 1024 * 1024;
			
			$bytes = ceil($this->BitsPerPixel / 8);
			$source_size = 0;
			$target_size = 0;
			
			if ($this->MimeType == "image/jpg" || $this->MimeType == "image/jpeg" || $this->Type == IMAGETYPE_JPEG) {
				$source_size = ceil($this->Width * $this->Height * $bytes * $this->ColorChannels * 2);
				$target_size = ceil($width * $height * $bytes * $this->ColorChannels * 2);
			} elseif ($this->MimeType == "image/gif" || $this->Type == IMAGETYPE_GIF) {
				$source_size = ceil($this->Width * $this->Height * $bytes * $this->ColorChannels * 2.5);
				$target_size = ceil($width * $height * $bytes * $this->ColorChannels * 2.5);
			} elseif ($this->MimeType == "image/png" || $this->Type == IMAGETYPE_PNG) {
				$source_size = ceil($this->Width * $this->Height * $bytes * $this->ColorChannels * 2.45);
				$target_size = ceil($width * $height * $bytes * $this->ColorChannels * 2.45);
			}
			
			// Add 2MB for PHP
			$memory_usage = (2 * 1024 * 1024) + $source_size + $target_size + memory_get_usage();
			
			if ($memory_usage > $available_memory) {
				return false;
			}
			
			return true;
		}
		
		// Cleans up crops, center crops, etc to make sure values are numeric and present
		private function cleanSettings() {
			if (empty($this->Settings["crops"]) || !is_array($this->Settings["crops"])) {
				$this->Settings["crops"] = [];
			}
			
			if (empty($this->Settings["thumbs"]) || !is_array($this->Settings["thumbs"])) {
				$this->Settings["thumbs"] = [];
			}
			
			if (empty($this->Settings["center_crops"]) || !is_array($this->Settings["center_crops"])) {
				$this->Settings["center_crops"] = [];
			}

			$this->Settings["retina"] = !empty($this->Settings["retina"]) ? true : false;
			
			foreach ($this->Settings["crops"] as $index => $crop) {
				$crop = $this->_cleanCrop($crop);

				if (is_null($crop)) {
					unset($this->Settings["crops"][$index]);
				} else {
					$this->Settings["crops"][$index] = $crop;
				}
			}
			
			foreach ($this->Settings["center_crops"] as $index => $crop) {
				$crop = $this->_cleanCrop($crop);

				if (is_null($crop)) {
					unset($this->Settings["center_crops"][$index]);
				} else {
					$this->Settings["center_crops"][$index] = $crop;
				}
			}
			
			foreach ($this->Settings["thumbs"] as $index => $thumbnail) {
				if (!$this->_cleanThumbnail($thumbnail)) {
					unset($this->Settings["thumbs"][$index]);
				}
			}
		}
		
		private function _cleanCrop($crop, $recursion = false) {
			if (!is_array($crop)) {
				return null;
			}
			
			if (empty($crop["width"]) || empty($crop["height"]) || !is_numeric($crop["width"]) || !is_numeric($crop["height"])) {
				return null;
			}

			$crop["grayscale"] = !empty($crop["grayscale"]) ? true : false;
			
			if (!$recursion) {
				if (empty(is_array($crop["thumbs"])) || !is_array($crop["thumbs"])) {
					$crop["thumbs"] = [];
				}
				
				if (empty($crop["center_crops"]) || !is_array($crop["center_crops"])) {
					$crop["center_crops"] = [];
				}
				
				foreach ($crop["thumbs"] as $index => $thumb) {
					if (!$this->_cleanThumbnail($thumb)) {
						unset($crop["thumbs"][$index]);
					}
				}
				
				foreach ($crop["center_crops"] as $index => $center_crop) {
					if (!$this->_cleanCrop($center_crop, true)) {
						unset($crop["center_crops"][$index]);
					}
				}
			}
			
			return $crop;
		}
		
		private function _cleanThumbnail($thumbnail) {
			if (
				!is_array($thumbnail) ||
				(empty($thumbnail["width"]) && empty($thumbnail["height"])) ||
				(!empty($thumbnail["width"]) && !is_numeric($thumbnail["width"])) ||
				(!empty($thumbnail["height"]) && !is_numeric($thumbnail["height"]))
			) {
				return null;
			}
			
			return $thumbnail;
		}
		
		/*
			Function: copy
				Copies the image to a new location.
			
			Parameters:
				location - The new location (leave empty to copy to a temporary file).
		
			Returns:
				A new BigTreeImage object for the new file or null if copy failed.
		*/
		
		public function copy($location = null) {
			if (is_null($location)) {
				$location = $this->getTempFileName();
			}

			$success = BigTree::copyFile($this->File, $location);
			
			if ($success) {
				return new BigTreeImage($location, $this->Settings, $this->IgnoringMinimums);
			}
			
			return null;
		}
		
		/*
			Function: crop
				Creates a cropped image.

			Parameters:
				location - The location to save the new cropped image (pass null to replace the source image).
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
		
		public function crop($location, $x, $y, $target_width, $target_height, $width, $height, $retina = false, $grayscale = false) {
			global $bigtree;

			static::setMemoryLimit();
			
			// If we don't have the memory available, fail gracefully.
			if (!$this->checkMemory($target_width, $target_height)) {
				static::restoreMemoryLimit();
				
				return false;
			}
			
			$jpeg_quality = isset($bigtree["config"]["image_quality"]) ? $bigtree["config"]["image_quality"] : 90;
			
			// If we're doing a retina image we're going to check to see if the cropping area is at least twice the desired size
			if ($retina && ($x + $width) >= $target_width * 2 && ($y + $height) >= $target_height * 2) {
				$jpeg_quality = isset($bigtree["config"]["retina_image_quality"]) ? $bigtree["config"]["retina_image_quality"] : 25;
				$target_width *= 2;
				$target_height *= 2;
			}
			
			$cropped_image = imagecreatetruecolor($target_width, $target_height);
			
			if ($this->Type == IMAGETYPE_JPEG) {
				$original_image = imagecreatefromjpeg($this->File);
			} elseif ($this->Type == IMAGETYPE_GIF) {
				$original_image = imagecreatefromgif($this->File);
			} elseif ($this->Type == IMAGETYPE_PNG) {
				$original_image = imagecreatefrompng($this->File);
			} else {
				static::restoreMemoryLimit();
				
				return false;
			}
			
			imagealphablending($original_image, true);
			imagealphablending($cropped_image, false);
			imagesavealpha($cropped_image, true);
			imagecopyresampled($cropped_image, $original_image, 0, 0, $x, $y, $target_width, $target_height, $width, $height);
			
			if ($grayscale) {
				imagefilter($cropped_image, IMG_FILTER_GRAYSCALE);
			}
			
			// Overwrite the original
			if (empty($location)) {
				$location = $this->File;
				$this->Width = $target_width;
				$this->Height = $target_height;
			}
			
			if ($this->Type == IMAGETYPE_JPEG) {
				imagejpeg($cropped_image, $location, $jpeg_quality);
			} elseif ($this->Type == IMAGETYPE_GIF) {
				imagegif($cropped_image, $location);
			} elseif ($this->Type == IMAGETYPE_PNG) {
				imagepng($cropped_image, $location);
			}
			
			BigTree::setPermissions($location);
			
			imagedestroy($original_image);
			imagedestroy($cropped_image);
			
			static::restoreMemoryLimit();
			
			return $location;
		}
		
		/*
			Function: destroy
				Deletes the source image.
		*/
		
		public function destroy() {
			@unlink($this->File);
			
			$this->Error = "The file has been deleted.";
			$this->Width = 0;
			$this->Height = 0;
			$this->Type = 0;
		}
		
		/*
			Function: fixRotation
				Checks EXIF image data and rotates the image if rotation flags are set.
		*/
		
		public function fixRotation() {
			if ($this->Type != IMAGETYPE_JPEG || !function_exists("exif_read_data")) {
				return;
			}
			
			$exif = @exif_read_data($this->File);
			
			if ($exif['Orientation'] == 3 || $exif['Orientation'] == 6 || $exif['Orientation'] == 8) {
				$source = imagecreatefromjpeg($this->File);
				
				if ($exif['Orientation'] == 3) {
					$source = imagerotate($source, 180, 0);
				} elseif ($exif['Orientation'] == 6) {
					$source = imagerotate($source, 270, 0);
				} else {
					$source = imagerotate($source, 90, 0);
				}
				
				imagejpeg($source, $this->File, 90);
				
				// Clean up memory
				imagedestroy($source);
				
				// Get new width/height/type
				list($this->Width, $this->Height) = getimagesize($this->File);
			}
		}
		
		/*
			Function: filterGeneratableCrops
				Filters the array of crops to only those which can be generated by this image.
		*/
		
		public function filterGeneratableCrops() {
			foreach ($this->Settings["crops"] as $crop_index => $crop) {
				$crop = $this->_generatableCropsFilter($crop);
				
				if ($crop) {
					$this->Settings["crops"][$crop_index] = $crop;
				} else {
					unset($this->Settings["crops"][$crop_index]);
				}
			}
			
			foreach ($this->Settings["center_crops"] as $crop_index => $crop) {
				$crop = $this->_generatableCropsFilter($crop);
				
				if ($crop) {
					$this->Settings["center_crops"][$crop_index] = $crop;
				} else {
					unset($this->Settings["center_crops"][$crop_index]);
				}
			}
		}
		
		private function _generatableCropsFilter($crop) {
			if ($this->Width >= $crop["width"] && $this->Height >= $crop["height"]) {
				return $crop;
			}
			
			if (!is_array($crop["thumbs"]) || !count($crop["thumbs"])) {
				return null;
			}
			
			// See if we can make one of the thumbnails of the crop the new primary
			$largest_width = 0;
			$largest_height = 0;
			$largest_index = null;
			$largest_prefix = null;
			$cleaned_thumbs = [];
				
			foreach ($crop["thumbs"] as $thumb_index => $thumb) {
				$size = $this->getThumbnailSize($thumb["width"], $thumb["height"], $crop["width"], $crop["height"]);
				
				if ($size["width"] <= $this->Width && $size["height"] <= $this->Height) {
					$cleaned_thumbs[$thumb_index] = $thumb;
					
					if ($largest_width < $size["width"]) {
						$largest_width = $size["width"];
						$largest_height = $size["height"];
						$largest_prefix = $thumb["prefix"];
						$largest_index = $thumb_index;
					}
				}
			}
			
			// No thumbnails work either
			if (!count($cleaned_thumbs)) {
				return null;
			}
			
			// Elevate the largest thumbnail to primary
			unset($cleaned_thumbs[$largest_index]);
			$crop["width"] = $largest_width;
			$crop["height"] = $largest_height;
			$crop["prefix"] = $largest_prefix;
			$crop["thumbs"] = $cleaned_thumbs;
			
			// Remove any center crops that won't work with the new primary
			if (is_array($crop["center_crops"]) && count($crop["center_crops"])) {
				foreach ($crop["center_crops"] as $center_index => $center_crop) {
					if ($center_crop["width"] > $largest_width || $center_crop["height"] > $largest_height) {
						unset($crop["center_crops"][$center_index]);
					}
				}
			}
			
			return $crop;
		}
		
		/*
			Function: getLargestCrop
				Finds the largest crop from a crop set and returns the width and height dimensions.
		
			Returns:
				An array with width and height keys.
		*/
		
		public function getLargestCrop() {
			$largest = 0;
			$largest_dims = [];
			
			if (is_array($this->Settings["crops"])) {
				foreach ($this->Settings["crops"] as $crop) {
					if ($crop["width"] > $this->Width || $crop["height"] > $this->Height) {
						continue;
					}
					
					if ($crop["width"] * $crop["height"] > $largest) {
						$largest = $crop["width"] * $crop["height"];
						$largest_dims = ["width" => $crop["width"], "height" => $crop["height"]];
					}
				}
			}
			
			if (is_array($this->Settings["center_crops"])) {
				foreach ($this->Settings["center_crops"] as $crop) {
					if ($crop["width"] > $this->Width || $crop["height"] > $this->Height) {
						continue;
					}
					
					if ($crop["width"] * $crop["height"] > $largest) {
						$largest = $crop["width"] * $crop["height"];
						$largest_dims = ["width" => $crop["width"], "height" => $crop["height"]];
					}
				}
			}
			
			return $largest_dims;
		}
		
		/*
			Function: getLargestThumbnail
				Finds the largest thumbnail from a crop set and returns the width and height dimensions.
		
			Returns:
				An array with width and height keys.
		*/
		
		public function getLargestThumbnail() {
			$largest = 0;
			$largest_dims = [];
			
			if (is_array($this->Settings["thumbnails"])) {
				foreach ($this->Settings["thumbnails"] as $thumbnail) {
					$dims = $this->getThumbnailSize($thumbnail["width"], $thumbnail["height"]);
					
					if ($dims["width"] * $dims["height"] > $largest) {
						$largest = $dims["width"] * $dims["height"];
						$largest_dims = ["width" => $dims["width"], "height" => $dims["height"]];
					}
				}
			}
			
			return $largest_dims;
		}
		
		/*
			Function: getPrefixArray
				Parses an array of crops, thumbnails, and center crops and returns an array of prefixes
			
			Returns:
				A modified array of file prefixes.
		*/
		
		public function getPrefixArray() {
			if (!empty($this->Prefixes)) {
				return $this->Prefixes;
			}
			
			$prefixes = [];
			
			if (is_array($this->Settings["crops"])) {
				foreach ($this->Settings["crops"] as $crop) {
					if ($crop["width"] > $this->Width || $crop["height"] > $this->Height) {
						continue;
					}
					
					if (!empty($crop["prefix"])) {
						$prefixes[] = $crop["prefix"];
					}
					
					if (is_array($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							if (!empty($thumb["prefix"])) {
								$prefixes[] = $thumb["prefix"];
							}
						}
					}
					
					if (is_array($crop["center_crops"])) {
						foreach ($crop["center_crops"] as $center_crop) {
							if (!empty($center_crop["prefix"])) {
								$prefixes[] = $center_crop["prefix"];
							}
						}
					}
				}
			}
			
			if (is_array($this->Settings["thumbs"])) {
				foreach ($this->Settings["thumbs"] as $thumb) {
					if (!empty($thumb["prefix"])) {
						$prefixes[] = $thumb["prefix"];
					}
				}
			}
			
			
			if (is_array($this->Settings["center_crops"])) {
				foreach ($this->Settings["center_crops"] as $crop) {
					if ($crop["width"] > $this->Width || $crop["height"] > $this->Height) {
						continue;
					}
					
					if (!empty($crop["prefix"])) {
						$prefixes[] = $crop["prefix"];
					}
					
					if (is_array($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							if (!empty($thumb["prefix"])) {
								$prefixes[] = $thumb["prefix"];
							}
						}
					}
				}
			}
			
			$this->Prefixes = $prefixes;
			
			return $prefixes;
		}
		
		
		/*
			Function: getThumbnailSize
				Returns a width and height that are constrained to the passed in max width and height.
	
			Parameters:
				max_width - The maximum width of the new image (0 for no max)
				max_height - The maximum height of the new image (0 for no max)
				width_override - Set another value to use for source width (defaults to $this->Width)
				height_override - Set another value to use for source height (defaults to $this->Height)
	
			Returns:
				An array with "width" and "height" keys.
		*/
		
		public function getThumbnailSize($max_width, $max_height, $width_override = null, $height_override = null) {
			$width = !is_null($width_override) ? $width_override : $this->Width;
			$height = !is_null($height_override) ? $height_override : $this->Height;
			
			if ($width > $max_width && $max_width) {
				$perc = $max_width / $width;
				$size["width"] = $max_width;
				$size["height"] = round($height * $perc, 0);
				
				if ($size["height"] > $max_height && $max_height) {
					$perc = $max_height / $size["height"];
					$size["height"] = $max_height;
					$size["width"] = round($size["width"] * $perc, 0);
				}
			} elseif ($height > $max_height && $max_height) {
				$perc = $max_height / $height;
				$size["height"] = $max_height;
				$size["width"] = round($width * $perc, 0);
				
				if ($size["width"] > $max_width && $max_width) {
					$perc = $max_width / $size["width"];
					$size["width"] = $max_width;
					$size["height"] = round($size["height"] * $perc, 0);
				}
			} else {
				$size["width"] = $width;
				$size["height"] = $height;
			}
			
			return ["width" => $size["width"], "height" => $size["height"]];
		}
		
		/*
			Function: getTempFileName
				Returns a temporary file name of the proper extension.
		*/
		
		public function getTempFileName() {
			$temp_file = SITE_ROOT."files/".uniqid("temp-").$this->Extensions[$this->Type];
			
			while (file_exists($temp_file)) {
				$temp_file = SITE_ROOT."files/".uniqid("temp-").$this->Extensions[$this->Type];
			}
			
			return $temp_file;
		}
		
		/*
			Function: getUpscaleSize
				Returns a width and height that are scaled up to the passed in min width and height.
	
			Parameters:
				min_width - The minimum width of the new image (0 for no min).
				min_height - The maximum height of the new image (0 for no min).
	
			Returns:
				An array with "width" and "height" keys.
		*/
		
		public function getUpscaleSize($min_width, $min_height) {
			if ($this->Width < $min_width && $min_width) {
				$perc = $min_width / $this->Width;
				$size["width"] = $min_width;
				$size["height"] = round($this->Height * $perc, 0);
				
				if ($size["height"] < $min_height && $min_height) {
					$perc = $min_height / $size["height"];
					$size["height"] = $min_height;
					$size["width"] = round($size["width"] * $perc, 0);
				}
			} elseif ($this->Height < $min_height && $min_height) {
				$perc = $min_height / $this->Height;
				$size["height"] = $min_height;
				$size["width"] = round($this->Width * $perc, 0);
				
				if ($size["width"] < $min_width && $min_width) {
					$perc = $min_width / $size["width"];
					$size["width"] = $min_width;
					$size["height"] = round($size["height"] * $perc, 0);
				}
			} else {
				$size["width"] = $this->Width;
				$size["height"] = $this->Height;
			}
			
			return ["width" => $size["width"], "height" => $size["height"]];
		}

		/*
			Function: processCenterCrops
				Parses the image settings center_crops array and runs/stores crops.
				Must be run after store() method has been called.
		*/
		
		public function processCenterCrops() {			
			foreach ($this->Settings["center_crops"] as $crop) {
				$temp = $this->getTempFileName();
				$this->centerCrop($temp, $crop["width"], $crop["height"], $this->Settings["retina"], $crop["grayscale"]);
				$this->Storage->replace($temp, $crop["prefix"].$this->StoredName, $this->Settings["directory"], true, $this->ForcingLocalReplace);
			}
		}
		
		/*
			Function: processCrops
				Parses the image settings crops array and runs any exact crops.
				Must be run after store() method has been called.
		
			Returns:
				An array of crops that will need to be processed.
		*/
		
		public function processCrops() {
			$crop_registry = [];
			
			foreach ($this->Settings["crops"] as $crop) {
				// If image dimensions are exactly what is asked for by the crop, do it immediately
				if ($this->Height == $crop["height"] && $this->Width == $crop["width"]) {
					// See if we want thumbnails
					if (is_array($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							$temp = $this->getTempFileName();
							$this->thumbnail($temp, $thumb["width"], $thumb["height"], $this->Settings["retina"], $thumb["grayscale"]);
							$this->Storage->replace($temp, $thumb["prefix"].$this->StoredName, $this->Settings["directory"], true, $this->ForcingLocalReplace);
						}
					}
					
					// See if we want center crops
					foreach ($crop["center_crops"] as $center_crop) {
						$temp = $this->getTempFileName();
						$this->centerCrop($temp, $center_crop["width"], $center_crop["height"], $this->Settings["retina"], $center_crop["grayscale"]);
						$this->Storage->replace($temp, $center_crop["prefix"].$this->StoredName, $this->Settings["directory"], true, $this->ForcingLocalReplace);
					}
					
					
					if ($crop["prefix"]) {
						$this->Storage->replace($this->File, $crop["prefix"].$this->StoredName, $this->Settings["directory"], false, $this->ForcingLocalReplace);
					}
				} else {
					$crop_registry[] = [
						"image" => $this->File,
						"directory" => $this->Settings["directory"],
						"retina" => $this->Settings["retina"],
						"name" => $this->StoredName,
						"width" => $crop["width"],
						"height" => $crop["height"],
						"prefix" => $crop["prefix"],
						"thumbs" => $crop["thumbs"],
						"center_crops" => $crop["center_crops"],
						"grayscale" => $crop["grayscale"],
					];
				}
			}
			
			foreach ($this->Settings["center_crops"] as $crop) {
				$temp = $this->getTempFileName();
				$this->centerCrop($temp, $crop["width"], $crop["height"], $this->Settings["retina"], $crop["grayscale"]);
				$this->Storage->replace($temp, $crop["prefix"].$this->StoredName, $this->Settings["directory"], true, $this->ForcingLocalReplace);
			}
			
			return $crop_registry;
		}
		
		/*
			Function: processThumbnails
				Parses the image settings thumbs array and runs/stores thumbnails.
				Must be run after store() method has been called.
		*/
		
		public function processThumbnails() {
			foreach ($this->Settings["thumbs"] as $thumb) {
				$temp = $this->getTempFileName();
				$this->thumbnail($temp, $thumb["width"], $thumb["height"], $this->Settings["retina"], $thumb["grayscale"]);
				$this->Storage->replace($temp, $thumb["prefix"].$this->StoredName, $this->Settings["directory"], true, $this->ForcingLocalReplace);
			}
		}
		
		/*
			Function: replace
				Stores a temporary file in its permanant location replacing any file that may exist at the location.
			
			Parameters:
				name - Desired file name
				force_local - true forces a local replacement (in the event the file is local but cloud is set as default)
			
			Returns:
				The full file path (and sets $this->StoredName) or null if the image failed to store (sets $this->Error).
		*/
		
		public function replace($name, $force_local = false) {
			$path = $this->Storage->replace($this->File, $name, $this->Settings["directory"], false, $force_local);
			
			if (!$path) {
				if ($this->Storage->DisabledFileError) {
					$this->Error = "Could not upload file. The file extension is not allowed.";
				} else {
					$this->Error = "Could not upload file. The destination is not writable.";
				}
				
				return null;
			}
			
			$stored_pathinfo = pathinfo($path);
			
			$this->ForcingLocalReplace = $force_local;
			$this->StoredName = $stored_pathinfo["basename"];
			
			return $path;
		}
		
		/*
			Function: store
				Stores a temporary file in its permanant location.
			
			Parameters:
				name - Desired file name
			
			Returns:
				The full file path (and sets $this->StoredName) or null if the image failed to store (sets $this->Error).
		*/
		
		public function store($name) {
			$path = $this->Storage->store($this->File, $name, $this->Settings["directory"], false, $this->getPrefixArray());
			
			if (!$path) {
				if ($this->Storage->DisabledFileError) {
					$this->Error = "Could not upload file. The file extension is not allowed.";
				} else {
					$this->Error = "Could not upload file. The destination is not writable.";
				}
				
				return null;
			}
			
			$stored_pathinfo = pathinfo($path);

			$this->StoredFile = $path;
			$this->StoredName = $stored_pathinfo["basename"];
			
			return $path;
		}
		
		/*
			Function: thumbnail
				Creates a thumbnailed image.
	
			Parameters:
				location - The location to save the new cropped image (pass null to replace the source image).
				width - The maximum width of the new image (0 for no max).
				height - The maximum height of the new image (0 for no max).
				retina - Whether to create a retina-style image (2x, lower quality) if able (defaults to false).
				grayscale - Whether to make the crop be in grayscale or not (defaults to false).
				upscale - If set to true, upscales to the width / height instead of downscaling (defaults to false, disables retina).
	
			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
	
			See Also:
				createUpscaledImage
		*/
		
		public function thumbnail($location, $width, $height, $retina = false, $grayscale = false, $upscale = false) {
			global $bigtree;
			
			$jpeg_quality = isset($bigtree["config"]["image_quality"]) ? $bigtree["config"]["image_quality"] : 90;
			
			static::setMemoryLimit();
			
			if ($upscale) {
				$size = $this->getUpscaleSize($width, $height);
			} else {
				$size = $this->getThumbnailSize($width, $height);
			}
			
			// If we're doing retina, see if 2x the height/width is less than the original height/width and change the quality.
			if ($retina && !$upscale && $size["width"] * 2 <= $this->Width && $size["height"] * 2 <= $this->Height) {
				$jpeg_quality = isset($bigtree["config"]["retina_image_quality"]) ? $bigtree["config"]["retina_image_quality"] : 25;
				$size["width"] *= 2;
				$size["height"] *= 2;
			}
			
			// If we don't have the memory available, fail gracefully.
			if (!$this->checkMemory($size["width"], $size["height"])) {
				static::restoreMemoryLimit();
				
				return false;
			}
			
			$thumbnailed_image = imagecreatetruecolor($size["width"], $size["height"]);
			
			if ($this->Type == IMAGETYPE_JPEG) {
				$original_image = imagecreatefromjpeg($this->File);
			} elseif ($this->Type == IMAGETYPE_GIF) {
				$original_image = imagecreatefromgif($this->File);
			} elseif ($this->Type == IMAGETYPE_PNG) {
				$original_image = imagecreatefrompng($this->File);
			} else {
				static::restoreMemoryLimit();
				
				return false;
			}
			
			imagealphablending($original_image, true);
			imagealphablending($thumbnailed_image, false);
			imagesavealpha($thumbnailed_image, true);
			imagecopyresampled($thumbnailed_image, $original_image, 0, 0, 0, 0, $size["width"], $size["height"], $this->Width, $this->Height);
			
			if ($grayscale) {
				imagefilter($thumbnailed_image, IMG_FILTER_GRAYSCALE);
			}
			
			// Overwrite the original
			if (empty($location)) {
				$location = $this->File;
				$this->Width = $size["width"];
				$this->Height = $size["height"];
			}
			
			if ($this->Type == IMAGETYPE_JPEG) {
				imagejpeg($thumbnailed_image, $location, $jpeg_quality);
			} elseif ($this->Type == IMAGETYPE_GIF) {
				imagegif($thumbnailed_image, $location);
			} elseif ($this->Type == IMAGETYPE_PNG) {
				imagepng($thumbnailed_image, $location);
			}
			
			BigTree::setPermissions($location);
			
			imagedestroy($original_image);
			imagedestroy($thumbnailed_image);
			
			static::restoreMemoryLimit();
			
			return $location;
		}
		
		/*
			Function: restoreMemoryLimit
				Restores the saved memory limit after image processing is complete.
		*/
		
		static function restoreMemoryLimit() {
			ini_set("memory_limit", static::$SavedMemoryLimit);
		}
		
		/*
			Function: setImageMemoryLimit
				Increases the memory limit of PHP for image processing and saves the current limit.
		*/
		
		static function setMemoryLimit() {
			global $bigtree;
			
			if (is_null(static::$SavedMemoryLimit)) {
				static::$SavedMemoryLimit = ini_get("memory_limit");
			}
			
			$image_memory_limit = !empty($bigtree["config"]["image_memory_limit"]) ? $bigtree["config"]["image_memory_limit"] : "256M";
			ini_set("memory_limit", $image_memory_limit);
		}
		
		/*
			Function: upscale
				Creates a upscaled image from a source image.
	
			Parameters:
				location - The location to save the new cropped image (pass null to replace the source image).
				min_width - The minimum width of the new image (0 for no max).
				min_height - The minimum height of the new image (0 for no max).
	
			Returns:
				The new file name if successful, false if there was not enough memory available or an invalid source image was provided.
	
			See Also:
				createThumbnail
		*/
		
		public function upscale($location, $min_width, $min_height) {
			return $this->thumbnail($location, $min_width, $min_height, false, false, true);
		}
		
	}
