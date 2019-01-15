<?php
	$media_settings = BigTreeJSONDB::get("config", "media-settings");
	$presets = $media_settings["presets"];

	include BigTree::path("admin/field-types/_image-preset.php");
