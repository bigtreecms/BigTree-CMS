<?php
	// BigTree 4.3 -- prerelease
	
	SQL::query("ALTER TABLE `bigtree_feeds` CHANGE COLUMN `options` `settings` LONGTEXT");
	SQL::query("ALTER TABLE `bigtree_module_views` CHANGE COLUMN `options` `settings` LONGTEXT");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 302);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 3"
	]);
	