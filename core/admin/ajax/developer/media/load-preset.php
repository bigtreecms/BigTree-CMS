<?php
	$media_settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
	$presets = $media_settings["presets"];

	include BigTree::path("admin/field-types/_image-preset.php");
