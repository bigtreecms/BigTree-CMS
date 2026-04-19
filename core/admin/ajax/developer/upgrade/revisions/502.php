<?php
	// BigTree 4.6

	// Adds the new passkeys table
	SQL::query("CREATE TABLE `bigtree_user_passkeys` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user` int(10) unsigned NOT NULL, `credential_id` text NOT NULL, `public_key` text NOT NULL, `sign_count` int(10) unsigned NOT NULL DEFAULT '0', `name` varchar(255) NOT NULL DEFAULT '', `aaguid` varchar(36) NOT NULL DEFAULT '', `transports` varchar(255) NOT NULL DEFAULT '', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_used` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `user_idx` (`user`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.6"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 502);
