<?php
	$admin->verifyCSRFToken();
	
	// Get existing presets
	$settings = BigTreeJSONDB::get("config", "media-settings");

	// Delete one of them
	unset($settings["presets"][$_POST["id"]]);
	BigTreeJSONDB::update("config", "media-settings", $settings);
