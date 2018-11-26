<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `video_data` longtext DEFAULT NULL AFTER `thumbs`");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `size` `size` int(11) unsigned DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `height` `height` int(11) unsigned DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `width` `width` int(11) unsigned DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `md5` `md5` varchar(255) DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `mimetype` `mimetype` varchar(255) DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE COLUMN `location` `location` varchar(255) DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` DROP COLUMN `list_thumb_margin`");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 306);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 7"
	]);
	