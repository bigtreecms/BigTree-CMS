<?php
	namespace BigTree;
	
	CSRF::verify();

	// Get existing presets
	$media_settings = new Setting("bigtree-internal-media-settings");

	// New preset? Create a unique ID
	if (!$_POST["id"]) {
		$id = uniqid();

		while (isset($media_settings->Value["presets"][$id])) {
			$id = uniqid();
		}

		$_POST["id"] = $id;
	}

	// Add preset
	$media_settings->Value["presets"][$_POST["id"]] = $_POST;

	// Alphabetize the presets
	$names = array();

	foreach ($media_settings->Value["presets"] as $preset) {
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
			$media_settings->Value["presets"][$index] = $preset;
		} else {
			unset($settings["presets"][$index]);
		}
	}

	array_multisort($names, $media_settings->Value["presets"]);

	$media_settings->save();

	// Return ID for adding presets
	echo $_POST["id"];