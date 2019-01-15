<?php
	// BigTree 4.3 -- prerelease
	
	SQL::query("CREATE TABLE `bigtree_sessions` (`id` varchar(32) NOT NULL,`last_accessed` int(10) unsigned DEFAULT NULL,`data` longtext,`is_login` char(2) NOT NULL DEFAULT '',PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 304);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 5"
	]);
	