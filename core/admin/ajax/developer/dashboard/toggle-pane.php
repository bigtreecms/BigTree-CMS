<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");
	
	// Toggle it
	$id = $_POST["id"];
	if (isset($settings[$id])) {
		if ($_POST["state"] == "true") {
			$settings[$id]["disabled"] = "";
		} else {
			$settings[$id]["disabled"] = "on";
		}
	} else {
		$settings[$id] = array("position" => 0,"disabled" => "on");
	}

	$admin->updateSettingValue("bigtree-internal-dashboard-settings",$settings);