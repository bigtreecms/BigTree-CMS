<?php
	// BigTree 4.5.5
	
	// Allow 404s/301s to be up to 1024 characters instead of 255
	SQL::query("ALTER TABLE `bigtree_404s` MODIFY COLUMN `broken_url` VARCHAR(1024)");
	SQL::query("ALTER TABLE `bigtree_404s` MODIFY COLUMN `get_vars` VARCHAR(1024)");
	SQL::query("ALTER TABLE `bigtree_404s` MODIFY COLUMN `redirect_url` VARCHAR(1024)");
	
	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.5.5"
	]);
	
	$admin->updateInternalSettingValue("bigtree-internal-revision", 501);
