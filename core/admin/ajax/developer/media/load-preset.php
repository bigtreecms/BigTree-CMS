<?php
	namespace BigTree;

	$media_settings = DB::get("config", "media-settings");
	$presets = $media_settings["presets"];
	$image_options_prefix = !empty($_POST["prefix"]) ? $_POST["prefix"] : null;
	
	include Router::getIncludePath("admin/field-types/_image-preset.php");
