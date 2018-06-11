<?php
	$settings = $cms->getSetting("bigtree-internal-media-settings");
	$preset = $settings["presets"]["default"];
	$crop_prefixes = [];
	$thumb_prefixes = [];

	if (is_array($preset["crops"])) {
		foreach ($preset["crops"] as $crop) {
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
			if ($thumb["prefix"]) {
				$thumb_prefixes[$thumb["prefix"]] = BigTree::calculateThumbnailSizes($width, $height, $thumb["width"], $thumb["height"]);
			}
		}
	}
