<?php
	// BigTree 4.5

	// Add expiration time to cache table
	SQL::query("ALTER TABLE `bigtree_caches` ADD COLUMN `expires` TIMESTAMP DEFAULT NULL AFTER `timestamp`");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.5"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 500);
