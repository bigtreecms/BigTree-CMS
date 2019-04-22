<?php
	// BigTree 4.4.5

	SQL::query("ALTER TABLE `bigtree_open_graph` ADD COLUMN `image_width` INT(11) DEFAULT NULL AFTER `image`");
	SQL::query("ALTER TABLE `bigtree_open_graph` ADD COLUMN `image_height` INT(11) DEFAULT NULL AFTER `image_width`");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.5"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 407);
