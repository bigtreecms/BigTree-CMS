<?php
	namespace BigTree;

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
		$names[] = $preset["name"];
	}

	array_multisort($names, $media_settings->Value["presets"]);

	$media_settings->save();

	// Return ID for adding presets
	echo $_POST["id"];