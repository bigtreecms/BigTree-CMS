<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_settings` CHANGE COLUMN `options` `settings` LONGTEXT");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 301);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 2"
	]);
	