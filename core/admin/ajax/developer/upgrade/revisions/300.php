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
	$settings["presets"]["default"] = json_decode('{"name":"File Manager Preset","min_width":"1440","min_height":"1080","preview_prefix":"classic-xxsml-","crops":[{"prefix":"ultrawide-xlrg-","width":"1440","height":"617","grayscale":"","thumbs":{"1":{"prefix":"ultrawide-xxsml-","width":"300","height":"","grayscale":""},"2":{"prefix":"ultrawide-xsml-","width":"500","height":"","grayscale":""},"3":{"prefix":"ultrawide-sml-","width":"740","height":"","grayscale":""},"4":{"prefix":"ultrawide-med-","width":"980","height":"","grayscale":""},"5":{"prefix":"ultrawide-lrg-","width":"1220","height":"","grayscale":""}}},{"prefix":"wide-xlrg-","width":"1440","height":"810","grayscale":"","thumbs":{"6":{"prefix":"wide-xxsml-","width":"300","height":"","grayscale":""},"7":{"prefix":"wide-xsml-","width":"500","height":"","grayscale":""},"8":{"prefix":"wide-sml-","width":"740","height":"","grayscale":""},"9":{"prefix":"wide-med-","width":"980","height":"","grayscale":""},"10":{"prefix":"wide-lrg-","width":"1220","height":"","grayscale":""}}},{"prefix":"full-xlrg-","width":"1440","height":"1080","grayscale":"","thumbs":{"11":{"prefix":"full-xxsml-","width":"300","height":"","grayscale":""},"12":{"prefix":"full-xsml-","width":"500","height":"","grayscale":""},"13":{"prefix":"full-sml-","width":"740","height":"","grayscale":""},"14":{"prefix":"full-med-","width":"980","height":"","grayscale":""},"15":{"prefix":"full-lrg-","width":"1220","height":"","grayscale":""}}},{"prefix":"square-med-","width":"980","height":"980","grayscale":"","thumbs":{"16":{"prefix":"square-xxsml-","width":"300","height":"","grayscale":""},"17":{"prefix":"square-xsml-","width":"500","height":"","grayscale":""},"18":{"prefix":"square-sml-","width":"740","height":"","grayscale":""}}},{"prefix":"classic-xlrg-","width":"1440","height":"960","grayscale":"","thumbs":{"19":{"prefix":"classic-xxsml-","width":"300","height":"","grayscale":""},"20":{"prefix":"classic-xsml-","width":"500","height":"","grayscale":""},"21":{"prefix":"classic-sml-","width":"740","height":"","grayscale":""},"22":{"prefix":"classic-med-","width":"980","height":"","grayscale":""},"23":{"prefix":"classic-lrg-","width":"1220","height":"","grayscale":""}}},{"prefix":"portrait-full-med-","width":"735","height":"980","grayscale":"","thumbs":{"24":{"prefix":"portrait-full-xxsml-","width":"","height":"300","grayscale":""},"25":{"prefix":"portrait-full-xsml-","width":"","height":"500","grayscale":""},"26":{"prefix":"portrait-full-sml-","width":"","height":"740","grayscale":""}}},{"prefix":"portrait-classic-med-","width":"654","height":"980","grayscale":"","thumbs":{"27":{"prefix":"portrait-classic-xxsml-","width":"","height":"300","grayscale":""},"28":{"prefix":"portrait-classic-xsml-","width":"","height":"500","grayscale":""},"29":{"prefix":"portrait-classic-sml-","width":"","height":"740","grayscale":""}}}],"thumbs":[{"prefix":"","width":"3000","height":"3000","grayscale":""}],"id":"default"}', true);
	BigTreeAdmin::updateInternalSettingValue("bigtree-internal-media-settings", $settings);

	// Delete the field type cache
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 300);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 1"
	]);
	
	