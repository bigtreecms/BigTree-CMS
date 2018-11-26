<?php
	// BigTree 4.2.22
	
	// Add a location column to resources
	sqlquery("ALTER TABLE `bigtree_resources` ADD COLUMN `location` VARCHAR(255) NOT NULL AFTER `id`");

	// Try to infer the location of existing resources
	$q = sqlquery("SELECT * FROM bigtree_resources");

	while ($resource = sqlfetch($q)) {
		if (strpos($resource["file"], "{staticroot}") !== false) {
			$location = "local";
		} else {
			$location = "cloud";
		}

		sqlquery("UPDATE bigtree_resources SET location = '$location' WHERE id = '".$resource["id"]."'");
	}

	$admin->updateInternalSettingValue("bigtree-internal-revision", 210);
	
	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.2.22"
	]);
	