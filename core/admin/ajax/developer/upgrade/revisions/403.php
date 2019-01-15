<?php
	// BigTree 4.4 -- prerelease
	
	$metadata = BigTreeCMS::getSetting("bigtree-file-metadata-fields");
	$metadata["id"] = "file-metadata";

	BigTreeJSONDB::insert("config", $metadata);

	$media_settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
	$media_settings["id"] = "media-settings";

	BigTreeJSONDB::insert("config", $media_settings);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4 revision 4"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 403);
