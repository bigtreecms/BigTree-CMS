<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	// Toggle it
	$id = $_POST["id"];
	if (isset($settings[$type][$id])) {
		if ($_POST["state"] == "true") {
			$settings[$type][$id]["disabled"] = "";
		} else {
			$settings[$type][$id]["disabled"] = "on";
		}
	} else {
		$settings[$type][$id] = array("position" => 0,"disabled" => "on");
	}

	$admin->updateSettingValue("bigtree-internal-extension-settings",$settings);