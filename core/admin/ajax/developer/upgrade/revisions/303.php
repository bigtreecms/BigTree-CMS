<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_users` ADD COLUMN `new_hash` CHAR(2) NOT NULL AFTER `password`");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 303);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 4"
	]);
	