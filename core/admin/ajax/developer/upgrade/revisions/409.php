<?php
	// BigTree 4.4.10

	// If open graph doesn't exist because of the bad base SQL, create it
	SQL::query("CREATE TABLE IF NOT EXISTS `bigtree_open_graph` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(255) NOT NULL DEFAULT '', `entry` int(11) unsigned NOT NULL, `type` varchar(255) DEFAULT NULL, `title` varchar(255) DEFAULT NULL, `description` text, `image` varchar(255) DEFAULT NULL, `image_width` int(11) DEFAULT NULL, `image_height` int(11) DEFAULT NULL, PRIMARY KEY (`id`), KEY `table` (`table`), KEY `entry` (`entry`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
	
	// Add indexes to open graph table
	SQL::query("ALTER TABLE `bigtree_open_graph` ADD KEY `entry` (`entry`)");
	SQL::query("ALTER TABLE `bigtree_open_graph` ADD KEY `table` (`table`)");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.10"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 409);
