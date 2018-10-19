<?php
	/**
	 * @global BigTreeImage $image
	 */

	$crop_prefixes = [];
	$thumb_prefixes = [];

	foreach ($image->Settings["crops"] as $crop_index => $crop) {
		if ($crop["prefix"]) {
			$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
		}

		if (is_array($crop["thumbs"])) {
			foreach ($crop["thumbs"] as $thumb) {
				if ($thumb["prefix"]) {
					$crop_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"], $crop["width"], $crop["height"]);
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

	foreach ($image->Settings["center_crops"] as $crop) {
		if ($crop["prefix"]) {
			$crop_prefixes[$crop["prefix"]] = ["width" => $crop["width"], "height" => $crop["height"]];
		}

		if (is_array($crop["thumbs"])) {
			foreach ($crop["thumbs"] as $thumb) {
				if ($thumb["prefix"]) {
					$crop_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"], $crop["width"], $crop["height"]);
				}
			}
		}
	}

	foreach ($image->Settings["thumbs"] as $thumb) {
		if ($thumb["prefix"]) {
			$thumb_prefixes[$thumb["prefix"]] = $image->getThumbnailSize($thumb["width"], $thumb["height"]);
		}
	}
