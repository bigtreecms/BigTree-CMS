<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_users` ADD COLUMN `timezone` VARCHAR(255) NOT NULL AFTER `daily_digest`");
	SQL::query("ALTER TABLE `bigtree_pages` CHANGE COLUMN `publish_at` `publish_at` DATETIME NULL");
	SQL::query("ALTER TABLE `bigtree_pages` CHANGE COLUMN `expire_at` `expire_at` DATETIME NULL");
	
	$admin->updateInternalSettingValue("bigtree-internal-revision", 309);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 10"
	]);
	