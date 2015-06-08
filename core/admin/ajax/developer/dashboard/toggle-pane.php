<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");
	
	// Toggle it
	$id = $_GET["id"];
	if (isset($settings[$id])) {
		if ($settings[$id]["disabled"]) {
			$settings[$id]["disabled"] = "";
		} else {
			$settings[$id]["disabled"] = "on";
		}
	} else {
		$settings[$id] = array("position" => 0,"disabled" => "on");
	}

	$admin->updateSettingValue("bigtree-internal-dashboard-settings",$settings);