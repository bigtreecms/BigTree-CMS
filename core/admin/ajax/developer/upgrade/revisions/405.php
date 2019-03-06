<?php
	// BigTree 4.4.2
	
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `last_updated` DATETIME DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `file_last_updated` DATETIME DEFAULT NULL");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.2"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 405);
