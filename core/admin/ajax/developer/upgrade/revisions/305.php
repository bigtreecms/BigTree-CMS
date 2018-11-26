<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_sessions` ADD COLUMN `logged_in_user` int(11) unsigned DEFAULT NULL AFTER `is_login`");
	SQL::query("ALTER TABLE `bigtree_sessions` ADD CONSTRAINT fk_logged_in_user FOREIGN KEY (`logged_in_user`) REFERENCES bigtree_users(id) ON DELETE CASCADE");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 305);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 6"
	]);
	