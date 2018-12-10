<?php
	$admin->verifyCSRFToken();
	
	// Get existing presets
	$settings = BigTreeJSONDB::get("config", "media-settings");

	// New preset? Create a unique ID
	if (!$_POST["id"]) {
		$id = uniqid();

		while (isset($settings["presets"][$id])) {
			$id = uniqid();
		}

		$_POST["id"] = $id;
	} else {
		$id = $_POST["id"];
	}

	// Add preset
	unset($_POST[$admin->CSRFTokenField]);
	$settings["presets"][$id] = $_POST;

	// Alphabetize the presets
	$names = array();

	foreach ($settings["presets"] as $index => $preset) {
		// Clean up empty entries
		if (is_array($preset["crops"])) {
			foreach ($preset["crops"] as $crop_index => $crop) {
				if (is_array($crop["center_crops"])) {
					foreach ($crop["center_crops"] as $center_crop_index => $center_crop) {
						if (!is_array($center_crop) || !array_filter($center_crop)) {
							unset($crop["center_crops"][$center_crop_index]);
						}
					}
				}
	
				if (is_array($crop["thumbs"])) {
					foreach ($crop["thumbs"] as $thumb_index => $thumb) {
						if (!is_array($thumb) || !array_filter($thumb)) {
							unset($crop["thumbs"][$thumb_index]);
						}
					}
				}
	
				if (!array_filter($crop)) {
					unset($preset["crops"]["crop_index"]);
				} else {
					$preset["crops"][$crop_index] = $crop;
				}
			}
		}

		if (is_array($preset["thumbs"])) {
			foreach ($preset["thumbs"] as $thumb_index => $thumb) {
				if (!array_filter($thumb)) {
					unset($preset["thumbs"][$thumb_index]);
				}
			}
		}

		if (is_array($preset["center_crops"])) {
			foreach ($preset["center_crops"] as $crop_index => $crop) {
				if (!array_filter($crop)) {
					unset($preset["center_crops"][$crop_index]);
				}
			}
		}

		// Only store it if this preset has stuff in it
		if (array_filter($preset)) {
			$names[] = $preset["name"];
			$settings["presets"][$index] = $preset;
		} else {
			unset($settings["presets"][$index]);
		}
	}

	array_multisort($names, $settings["presets"]);

	// Update Settings
	BigTreeJSONDB::update("config", "media-settings", $settings);

	// Return ID for adding presets
	echo $id;
