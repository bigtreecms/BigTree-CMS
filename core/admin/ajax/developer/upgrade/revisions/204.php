<?php
	// BigTree 4.2.19

	sqlquery("ALTER TABLE `bigtree_404s` ADD COLUMN `site_key` VARCHAR(255) NULL");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 204);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.2.19"
	]);
	