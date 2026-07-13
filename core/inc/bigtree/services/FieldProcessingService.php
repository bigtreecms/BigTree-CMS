<?php
	namespace BigTree\Services;

	use BigTreeCMS;
	use BigTree;
	use SQL;
	use BigTreeAutoModule;
	use BigTreeImage;
	use BigTreeJSONDB;
	use BigTreeStorage;

	/**
	 * Field process/crop/upload pipeline (cluster I).
	 */
	class FieldProcessingService {

		public static $FieldCache = [];

		public static function processField($field) {
			global $admin, $bigtree, $cms;

			// Preserve the public field-type process.php include contract.
			$admin = LegacyAdmin::bridge();

			// Make sure options is an array to prevent warnings, load from options as a fallback for < 4.3
			if (!is_array($field["settings"])) {
				if (is_array($field["options"]) && array_filter($field["options"])) {
					$field["settings"] = $field["options"];
				} else {
					$field["settings"] = [];
				}
			}

			$field["options"] = &$field["settings"];
			$field["output"] = "";

			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"] ?? "";

			// Check if the field type is stored in an extension
			if (strpos($field["type"], "*") !== false) {
				[$extension, $field_type] = explode("*", $field["type"]);

				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/process.php";
			} else {
				// < 4.3 location - we prefer it to allow old overrides to continue to work
				$field_type_path = SERVER_ROOT."custom/admin/form-field-types/process/".$field["type"].".php";

				if (!file_exists($field_type_path)) {
					$field_type_path = BigTree::path("admin/field-types/".$field["type"]."/process.php");
				}
			}

			// If we have a customized handler for this data type, run it.
			if (file_exists($field_type_path)) {
				include $field_type_path;

				// If it's explicitly ignored return null
				if (!empty($field["ignore"])) {
					return null;
				} else {
					$output = $field["output"];
				}

				// Fall back to default handling
			} else {
				if (is_array($field["input"])) {
					$output = $field["input"];
				} else {
					$output = BigTree::safeEncode($field["input"]);
				}
			}

			// Check validation
			if (!BigTreeAutoModule::validate($output, $field["settings"]["validation"] ?? "")) {
				$error = !empty($field["settings"]["error_message"]) ? $field["settings"]["error_message"] : BigTreeAutoModule::validationErrorMessage($output, $field["settings"]["validation"]);
				$bigtree["errors"][] = [
					"field" => $field["title"],
					"error" => $error
				];
			}

			// Translation of internal links
			if (is_array($output)) {
				$output = BigTree::translateArray($output);
			} else {
				$output = LinkService::autoIPL($output);
			}

			ResourceAllocationService::trackResourcesInValue($output);

			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];

			return $output;
		}

		public static function processImageUpload($field, $replace = false, $force_local_replace = false) {
			global $bigtree;

			$failed = false;
			$name = $field["file_input"]["name"];
			$temp_name = $field["file_input"]["tmp_name"];
			$error = $field["file_input"]["error"];

			// If a file upload error occurred, return the old image and set errors
			if ($error == 1 || $error == 2) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>"];

				return false;
			} elseif ($error == 3) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => "The file upload failed ($name)."];

				return false;
			}

			// Backwards compatibility with 4.2
			if (empty($field["settings"])) {
				$field["settings"] = $field["options"];
			}

			// See if we're using image presets
			if (!empty($field["settings"]["preset"])) {
				$media_settings = BigTreeJSONDB::get("config", "media-settings");
				$preset = $media_settings["presets"][$field["settings"]["preset"]];

				// If the preset still exists, copy its properties over to our options
				if ($preset) {
					foreach ($preset as $key => $val) {
						$field["settings"][$key] = $val;
					}
				}
			}

			// This is a file manager upload, add a 100x100 center crop
			if (!empty($field["settings"]["preset"]) && $field["settings"]["preset"] == "default") {
				if (empty($field["settings"]["center_crops"]) || !is_array($field["settings"]["center_crops"])) {
					$field["settings"]["center_crops"] = [];
				}

				$field["settings"]["center_crops"][] = [
					"prefix" => "list-preview/",
					"width" => 100,
					"height" => 100
				];
			}

			// Load up the image class for doing manipulation / calculation and fix any EXIF rotations
			$image = new BigTreeImage($temp_name, $field["settings"]);

			if ($image->Error) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => $image->Error];
				$image->destroy();

				return false;
			}

			// For crops that don't meet the required image size, see if a sub-crop will work.
			$image->filterGeneratableCrops();

			// Get largest crop and thumbnail to check if we have the memory available to make them
			$largest_thumb = $image->getLargestThumbnail();
			$largest_crop = $image->getLargestCrop();

			if (($largest_thumb && !$image->checkMemory($largest_thumb["width"], $largest_thumb["height"])) ||
				($largest_crop && !$image->checkMemory($largest_crop["width"], $largest_crop["height"]))
			) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => "The image uploaded is too large for the server to manipulate. Please upload a smaller version of this image"];
				$image->destroy();

				return false;
			}

			// Upload the original to the proper place.
			if ($replace) {
				$field["output"] = $image->replace($name, $force_local_replace);
			} else {
				$field["output"] = $image->store($name);
			}

			// If the upload service didn't return a value, we failed to upload it for one reason or another.
			if (!$field["output"]) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => $image->Error];
				$image->destroy();

				return false;
			}

			// Handle crops and thumbnails
			$crops = $image->processCrops();
			$image->processThumbnails();
			$image->processCenterCrops();

			// If we don't have any crops, get rid of the temporary image we made.
			if (!count($crops)) {
				$image->destroy();
			} else {
				if (!is_array($bigtree["crops"])) {
					$bigtree["crops"] = [];
				}

				$bigtree["crops"] = array_merge($bigtree["crops"], $crops);
			}

			return $field["output"];
		}

		public static function processCrop($crop_key, $index, $x, $y, $width, $height) {
			$storage = new BigTreeStorage;

			$crops = BigTreeCMS::cacheGet("org.bigtreecms.crops", $crop_key);
			$crop = $crops[$index];

			$image_src = $crop["image"];
			$target_width = $crop["width"];
			$target_height = $crop["height"];
			$thumbs = $crop["thumbs"];
			$center_crops = $crop["center_crops"];

			$image = new BigTreeImage($image_src);
			$temp_crop = $image->getTempFileName();
			$image->crop($temp_crop, $x, $y, $target_width, $target_height, $width, $height, $crop["retina"], $crop["grayscale"]);
			$temp_image = new BigTreeImage($temp_crop);

			// Make thumbnails for the crop
			if (is_array($thumbs)) {
				foreach ($thumbs as $thumb) {
					// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
					$temp_thumb = $temp_image->getTempFileName();
					$size = $temp_image->getThumbnailSize($thumb["width"], $thumb["height"]);
					$image->crop($temp_thumb, $x, $y, $size["width"], $size["height"], $width, $height, $crop["retina"], $thumb["grayscale"]);
					$storage->replace($temp_thumb, $thumb["prefix"].$crop["name"], $crop["directory"]);
				}
			}

			// Make center crops of the crop
			if (is_array($center_crops)) {
				foreach ($center_crops as $center_crop) {
					$temp_center_crop = $image->getTempFileName();
					$temp_image->centerCrop($temp_center_crop, $center_crop["width"], $center_crop["height"], $crop["retina"], $center_crop["grayscale"]);
					$storage->replace($temp_center_crop, $center_crop["prefix"].$crop["name"], $crop["directory"]);
				}
			}

			// Move crop into its resting place
			$storage->replace($temp_crop, $crop["prefix"].$crop["name"], $crop["directory"]);
		}

		public static function processCrops($crop_key) {
			$storage = new BigTreeStorage;

			// Get and remove the crop data
			$crops = BigTreeCMS::cacheGet("org.bigtreecms.crops", $crop_key);
			BigTreeCMS::cacheDelete("org.bigtreecms.crops", $crop_key);

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

				$image = new BigTreeImage($image_src);
				$temp_crop = $image->getTempFileName();
				$image->crop($temp_crop, $x, $y, $target_width, $target_height, $width, $height, $crop["retina"], $crop["grayscale"]);
				$temp_image = new BigTreeImage($temp_crop);

				// Make thumbnails for the crop
				if (is_array($thumbs)) {
					foreach ($thumbs as $thumb) {
						// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
						$temp_thumb = $temp_image->getTempFileName();
						$size = $temp_image->getThumbnailSize($thumb["width"], $thumb["height"]);
						$image->crop($temp_thumb, $x, $y, $size["width"], $size["height"], $width, $height, $crop["retina"], $thumb["grayscale"]);
						$storage->replace($temp_thumb, $thumb["prefix"].$crop["name"], $crop["directory"]);
					}
				}

				// Make center crops of the crop
				if (is_array($center_crops)) {
					foreach ($center_crops as $center_crop) {
						$temp_center_crop = $image->getTempFileName();
						$temp_image->centerCrop($temp_center_crop, $center_crop["width"], $center_crop["height"], $crop["retina"], $center_crop["grayscale"]);
						$storage->replace($temp_center_crop, $center_crop["prefix"].$crop["name"], $crop["directory"]);
					}
				}

				// Move crop into its resting place
				$storage->replace($temp_crop, $crop["prefix"].$crop["name"], $crop["directory"]);
			}

			// Remove all the temporary images
			foreach ($crops as $crop) {
				@unlink($crop["image"]);
			}
		}
	}
