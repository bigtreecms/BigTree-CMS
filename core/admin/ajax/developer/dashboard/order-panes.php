<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");
	
	foreach ($_POST["positions"] as $id => $position) {
		if (isset($settings[$id])) {
			$settings[$id]["position"] = $position;
		} else {
			$settings[$id] = array("position" => $position, "disabled" => "");
		}
	}

	$admin->updateSettingValue("bigtree-internal-dashboard-settings",$settings);