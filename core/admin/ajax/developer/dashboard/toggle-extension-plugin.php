<?php
	namespace BigTree;

	// Grab the settings list
	$extension_settings = new Setting("bigtree-internal-extension-settings");
	$type = $_POST["type"];
	
	// Toggle it
	$id = $_POST["id"];
	if (isset($extension_settings->Value[$type][$id])) {
		if ($_POST["state"] == "true") {
			$extension_settings->Value[$type][$id]["disabled"] = "";
		} else {
			$extension_settings->Value[$type][$id]["disabled"] = "on";
		}
	} else {
		$extension_settings->Value[$type][$id] = array("position" => 0, "disabled" => "on");
	}

	$extension_settings->save();