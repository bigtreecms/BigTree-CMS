<?php
	// BigTree 4.3 -- release

	SQL::query("ALTER TABLE `bigtree_sessions` ADD COLUMN `ip_address` VARCHAR(255) NOT NULL AFTER `last_accessed`");
	SQL::query("ALTER TABLE `bigtree_sessions` ADD COLUMN `user_agent` TEXT NOT NULL AFTER `ip_address`");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 313);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.3"
	]);
	