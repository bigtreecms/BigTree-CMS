<?php
	// BigTree 4.4.6

	// We need to drop the extension foreign key from the bigtree_settings table since bigtree_extensions no longer exists
	$description = SQL::describeTable("bigtree_settings");

	foreach ($description["foreign_keys"] as $index => $key) {
		SQL::query("ALTER TABLE `bigtree_settings` DROP FOREIGN KEY `$index`");
	}

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.6"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 408);
