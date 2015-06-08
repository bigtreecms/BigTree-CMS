<?php
	// Grab the settings list
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");

	// Parse out the posts
	parse_str($_POST["sort"]);
	$max = count($row);
	
	foreach ($row as $pos => $id) {
		$id = $_POST["rel"][$id];

		if (isset($settings[$id])) {
			$settings[$id]["position"] = $max - $pos;
		} else {
			$settings[$id] = array("position" => $max - $pos, "disabled" => "");
		}
	}

	$admin->updateSettingValue("bigtree-internal-dashboard-settings",$settings);