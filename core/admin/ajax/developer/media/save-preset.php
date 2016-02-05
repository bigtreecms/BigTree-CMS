<?
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
	foreach ($settings["presets"] as $preset) {
		$names[] = $preset["name"];
	}
	array_multisort($names,$settings["presets"]);

	// Update Settings
	$admin->updateSettingValue("bigtree-internal-media-settings",$settings);

	// Return ID for adding presets
	echo $id;
?>