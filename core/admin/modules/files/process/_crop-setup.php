<?php
	$settings = $cms->getSetting("bigtree-internal-media-settings");
	$preset = $settings["presets"]["default"];
	$dir = opendir(SITE_ROOT."files/temporary/".$admin->ID."/");
	$crop_prefixes = [];
	$thumb_prefixes = [];

	if (is_array($preset["crops"])) {
		foreach ($preset["crops"] as $crop) {
			if ($crop["prefix"]) {
				$crop_prefixes[] = $crop["prefix"];
			}

			if (is_array($crop["thumbs"])) {
				foreach ($crop["thumbs"] as $thumb) {
					if ($thumb["prefix"]) {
						$crop_prefixes[] = $thumb["prefix"];
					}
				}
			}

			if (is_array($crop["center_crops"])) {
				foreach ($crop["center_crops"] as $center_crop) {
					if ($center_crop["prefix"]) {
						$crop_prefixes[] = $center_crop["prefix"];
					}
				}
			}
		}
	}

	if (is_array($preset["center_crops"])) {
		foreach ($preset["center_crops"] as $crop) {
			if ($crop["prefix"]) {
				$crop_prefixes[] = $crop["prefix"];
			}

			if (is_array($crop["thumbs"])) {
				foreach ($crop["thumbs"] as $thumb) {
					if ($thumb["prefix"]) {
						$crop_prefixes[] = $thumb["prefix"];
					}
				}
			}
		}
	}

	if (is_array($preset["thumbs"])) {
		foreach ($preset["thumbs"] as $thumb) {
			if ($thumb["prefix"]) {
				$thumb_prefixes[] = $thumb["prefix"];
			}
		}
	}
