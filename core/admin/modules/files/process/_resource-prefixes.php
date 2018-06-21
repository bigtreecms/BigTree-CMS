<?php
	$settings = $cms->getSetting("bigtree-internal-media-settings");
	$preset = $settings["presets"]["default"];
	$crop_prefixes = [];
	$thumb_prefixes = [];

	if (is_array($preset["crops"])) {
		// For crops that don't meet the required image size, see if a sub-crop will work.
		if (is_array($preset["crops"])) {
			foreach ($preset["crops"] as $crop_index => $crop) {
				// Let's see if we can elevate another crop to the top.
				if (($width < $crop["width"] || $height < $crop["height"]) && is_array($crop["thumbs"]) && count($crop["thumbs"]))  {
					$largest_width = 0;
					$largest_height = 0;
					$largest_index = null;
					$largest_prefix = null;
					$cleaned_thumbs = [];
					
					foreach ($crop["thumbs"] as $thumb_index => $thumb) {
						$size = BigTree::calculateThumbnailSizes($crop["width"], $crop["height"], $thumb["width"], $thumb["height"]);
						
						if ($size["width"] <= $width && $size["height"] <= $height) {
							$cleaned_thumbs[$thumb_index] = $thumb;
							
							if ($largest_width < $size["width"]) {
								$largest_width = $size["width"];
								$largest_height = $size["height"];
								$largest_prefix = $thumb["prefix"];
								$largest_index = $thumb_index;
							}
						}
					}
					
					// We have some thumbs that we can make so we need to now elevate the largest thumb crop to the primary crop
					if (count($cleaned_thumbs)) {
						// Remove the largest from the thumbs array to not make it twice and then insert it as the primary
						unset($cleaned_thumbs[$largest_index]);
						$preset["crops"][$crop_index]["width"] = $largest_width;
						$preset["crops"][$crop_index]["height"] = $largest_height;
						$preset["crops"][$crop_index]["prefix"] = $largest_prefix;
						$preset["crops"][$crop_index]["thumbs"] = $cleaned_thumbs;
					}
					
					if (is_array($crop["center_crops"]) && count($crop["center_crops"])) {
						foreach ($crop["center_crops"] as $center_index => $center_crop) {
							if ($center_crop["width"] > $largest_width || $center_crop["height"] > $largest_height) {
								unset($crop["center_crops"][$center_index]);
							}
						}
					}
				}
			}
		}
		
		foreach ($preset["crops"] as $crop) {
			if ($crop["width"] > $width || $crop["height"] > $height) {
				continue;
			}
			
			if ($crop["prefix"]) {
				$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
			}

			if (is_array($crop["thumbs"])) {
				foreach ($crop["thumbs"] as $thumb) {
					if ($thumb["prefix"]) {
						$crop_prefixes[$thumb["prefix"]] = BigTree::calculateThumbnailSizes($crop["width"], $crop["height"], $thumb["width"], $thumb["height"]);
					}
				}
			}

			if (is_array($crop["center_crops"])) {
				foreach ($crop["center_crops"] as $center_crop) {
					if ($center_crop["prefix"]) {
						$crop_prefixes[$center_crop["prefix"]] = ["width" => $center_crop["width"], "height" => $center_crop["height"]];
					}
				}
			}
		}
	}

	if (is_array($preset["center_crops"])) {
		foreach ($preset["center_crops"] as $crop) {
			if ($crop["width"] > $width || $crop["height"] > $height) {
				continue;
			}
			
			if ($crop["prefix"]) {
				$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
			}

			if (is_array($crop["thumbs"])) {
				foreach ($crop["thumbs"] as $thumb) {
					if ($thumb["prefix"]) {
						$crop_prefixes[$thumb["prefix"]] = BigTree::calculateThumbnailSizes($crop["width"], $crop["height"], $thumb["width"], $thumb["height"]);
					}
				}
			}
		}
	}

	if (is_array($preset["thumbs"])) {
		foreach ($preset["thumbs"] as $thumb) {
			if ($thumb["width"] > $width && $thumb["height"] > $height) {
				continue;
			}
			
			if ($thumb["prefix"]) {
				$thumb_prefixes[$thumb["prefix"]] = BigTree::calculateThumbnailSizes($width, $height, $thumb["width"], $thumb["height"]);
			}
		}
	}
