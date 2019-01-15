<?php
	// BigTree 4.3 -- prerelease
	
	SQL::query("CREATE TABLE `bigtree_open_graph` ( `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(255) NOT NULL DEFAULT '', `entry` int(11) unsigned NOT NULL, `type` varchar(255) DEFAULT NULL, `title` varchar(255) DEFAULT NULL, `description` text, `image` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	SQL::query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `open_graph` CHAR(2) NOT NULL AFTER `return_url`");
	SQL::query("ALTER TABLE `bigtree_pending_changes` ADD COLUMN `open_graph_changes` LONGTEXT NOT NULL AFTER `tags_changes`");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 308);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 9"
	]);
	