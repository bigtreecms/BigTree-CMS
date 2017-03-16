<?
	$admin->verifyCSRFToken();
	
	// Get existing presets
	$settings = $cms->getSetting("bigtree-internal-media-settings");

	// New preset? Create a unique ID
	if (!$_POST["id"]) {
		$id = uniqid();

		while (isset($settings["presets"][$id])) {
			$id = uniqid();
		}

		$_POST["id"] = $id;
	}

	// Add preset
	$settings["presets"][$_POST["id"]] = $_POST;

	// Alphabetize the presets
	$names = array();

	foreach ($settings["presets"] as $index => $preset) {
		// Clean up empty entries
		foreach ($preset["crops"] as $crop_index => $crop) {
			foreach ($crop["center_crops"] as $center_crop_index => $center_crop) {
				if (!is_array($center_crop) || !array_filter($center_crop)) {
					unset($crop["center_crops"][$center_crop_index]);
				}
			}

			foreach ($crop["thumbs"] as $thumb_index => $thumb) {
				if (!is_array($thumb) || !array_filter($thumb)) {
					unset($crop["thumbs"][$thumb_index]);
				}
			}

			if (!array_filter($crop)) {
				unset($preset["crops"]["crop_index"]);
			} else {
				$preset["crops"][$crop_index] = $crop;
			}
		}

		foreach ($preset["thumbs"] as $thumb_index => $thumb) {
			if (!array_filter($thumb)) {
				unset($preset["thumbs"][$thumb_index]);
			}
		}

		foreach ($preset["center_crops"] as $crop_index => $crop) {
			if (!array_filter($crop)) {
				unset($preset["center_crops"][$crop_index]);
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
	$admin->updateSettingValue("bigtree-internal-media-settings",$settings);

	// Return ID for adding presets
	echo $id;
?>