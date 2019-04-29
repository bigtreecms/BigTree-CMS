<?php
	namespace BigTree;

	// Grab the settings list
	$data = Setting::value("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	foreach ($_POST["positions"] as $id => $position) {
		if (isset($data[$type][$id])) {
			$data[$type][$id]["position"] = $position;
		} else {
			$data[$type][$id] = ["position" => $position, "disabled" => ""];
		}
	}

	Setting::updateValue("bigtree-internal-extension-settings", $data);
