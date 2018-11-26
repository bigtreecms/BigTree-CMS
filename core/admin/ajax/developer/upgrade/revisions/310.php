<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_templates` ADD COLUMN `hooks` TEXT NOT NULL AFTER `resources`");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 310);
	
	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 11"
	]);
	