<?php
	namespace BigTree;

	// Grab the settings list
	$extension_settings = new Setting("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	foreach ($_POST["positions"] as $id => $position) {
		if (isset($extension_settings->Value[$type][$id])) {
			$extension_settings->Value[$type][$id]["position"] = $position;
		} else {
			$extension_settings->Value[$type][$id] = array("position" => $position, "disabled" => "");
		}
	}

	$extension_settings->save();
