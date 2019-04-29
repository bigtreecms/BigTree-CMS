<?php
	namespace BigTree;

	// Grab the settings list
	$extension_settings = Setting::value("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	// Toggle it
	$id = $_POST["id"];
	
	if (isset($extension_settings-[$type][$id])) {
		if ($_POST["state"] == "true") {
			$extension_settings[$type][$id]["disabled"] = "";
		} else {
			$extension_settings[$type][$id]["disabled"] = "on";
		}
	} else {
		$extension_settings[$type][$id] = ["position" => 0, "disabled" => "on"];
	}

	Setting::updateValue("bigtree-internal-extension-settings", $extension_settings);
