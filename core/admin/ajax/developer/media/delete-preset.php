<?php
	namespace BigTree;
	
	CSRF::verify();

	// Get existing presets
	$media_settings = new Setting("bigtree-internal-media-settings");

	// Delete one of them
	unset($media_settings->Value["presets"][$_POST["id"]]);
	$media_settings->save();
	