<?php
	namespace BigTree;
	
	CSRF::verify();
	
	// Get existing presets
	$settings = DB::get("config", "media-settings");
	
	// Delete one of them
	unset($settings["presets"][$_POST["id"]]);
	DB::update("config", "media-settings", $settings);
	AuditTrail::track("config:media-settings", "presets", "update", "deleted preset");
	