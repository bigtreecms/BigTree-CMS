<?php
	$media_settings = BigTreeJSONDB::get("config", "media-settings");
	$presets = $media_settings["presets"];
	$image_options_prefix = !empty($_POST["prefix"]) ? $_POST["prefix"] : null;
	
	include BigTree::path("admin/field-types/_image-preset.php");
