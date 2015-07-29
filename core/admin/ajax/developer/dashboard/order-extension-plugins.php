<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	foreach ($_POST["positions"] as $id => $position) {
		if (isset($settings[$type][$id])) {
			$settings[$type][$id]["position"] = $position;
		} else {
			$settings[$type][$id] = array("position" => $position, "disabled" => "");
		}
	}

	$admin->updateSettingValue("bigtree-internal-extension-settings",$settings);