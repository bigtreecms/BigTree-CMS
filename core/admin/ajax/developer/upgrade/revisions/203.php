<?php
	// BigTree 4.2.17

	sqlquery("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `csrf_token` VARCHAR(255) NULL");
	sqlquery("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `csrf_token_field` VARCHAR(255) NULL");
	sqlquery("DELETE FROM bigtree_user_sessions");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 203);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.2.17"
	]);
	