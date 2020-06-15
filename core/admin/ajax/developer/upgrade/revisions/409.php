<?php
	// BigTree 4.4.10
	
	// Add indexes to open graph table
	SQL::query("ALTER TABLE `bigtree_open_graph` ADD KEY `entry` (`entry`)");
	SQL::query("ALTER TABLE `bigtree_open_graph` ADD KEY `table` (`table`)");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.10"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 409);
