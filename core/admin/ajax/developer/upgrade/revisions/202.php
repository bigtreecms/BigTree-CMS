<?php
	// BigTree 4.2.10

	sqlquery("ALTER TABLE `bigtree_pending_changes` CHANGE COLUMN `user` `user` int(11) unsigned NULL");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 202);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.2.10"
	]);
	