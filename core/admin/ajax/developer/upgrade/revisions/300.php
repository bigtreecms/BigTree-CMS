<?php
	// BigTree 4.3 -- prerelease
	
	// Add a usage count column to tags
	SQL::query("ALTER TABLE `bigtree_tags` ADD COLUMN `usage_count` int(11) unsigned NOT NULL AFTER `route`");

	// Add a setting for storing file metadata information
	SQL::query("INSERT INTO bigtree_settings (`id`, `value`, `system`) VALUES ('bigtree-file-metadata-fields', '[]', 'on')");

	// Add new file manager columns
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `metadata` LONGTEXT NOT NULL AFTER `type`");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `is_video` CHAR(2) NOT NULL AFTER `is_image`");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `mimetype` VARCHAR(255) NOT NULL AFTER `type`");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `size` INT(11) NOT NULL AFTER `width`");
	SQL::query("UPDATE bigtree_resources SET metadata = '{}'");

	// Add the file manager preset to media presets
	$settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
	$settings["presets"]["default"] = json_decode('{
		"id": "default",
		"name": "File Manager Preset",
		"min_width": "",
		"min_height": "",
		"preview_prefix": "small-",
		"thumbs": [
			{
				"prefix": "small-",
				"width": "500",
				"height": "300",
				"grayscale": ""
			},
			{
				"prefix": "medium-",
				"width": "720",
				"height": "600",
				"grayscale": ""
			},
			{
				"prefix": "large-",
				"width": "1280",
				"height": "",
				"grayscale": ""
			}
		]
	}', true);
	BigTreeAdmin::updateInternalSettingValue("bigtree-internal-media-settings", $settings);

	// Delete the field type cache
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 300);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 1"
	]);
	
	