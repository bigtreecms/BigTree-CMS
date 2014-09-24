<?
	$admin->requireLevel(2);
	
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

	// Update Settings
	$settings["presets"][$_POST["id"]] = $_POST;
	$admin->updateSettingValue("bigtree-internal-media-settings",$settings);

	// Return ID for adding presets
	echo $id;
?>