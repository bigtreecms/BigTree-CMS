<?php
	// BigTree 4.4.2
	
	SQL::query("ALTER TABLE `bigtree_pending_changes` MODIFY `module` VARCHAR(255)");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.2"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 404);
