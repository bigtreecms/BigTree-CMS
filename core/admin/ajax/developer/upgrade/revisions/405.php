<?php
	namespace BigTree;
	
	// BigTree 4.4.2
	
	SQL::query("ALTER TABLE `bigtree_pending_changes` MODIFY `module` VARCHAR(255)");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `last_updated` DATETIME DEFAULT NULL");
	SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `file_last_updated` DATETIME DEFAULT NULL");

	echo JSON::encode([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.2"
	]);
	
	Setting::updateValue("bigtree-internal-revision", 405);
