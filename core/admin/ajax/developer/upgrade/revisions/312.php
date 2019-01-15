<?php
	// BigTree 4.3 -- prerelease

	SQL::query("ALTER TABLE `bigtree_resource_allocation` CHANGE COLUMN `module` `table` VARCHAR(255)");
	SQL::query("ALTER TABLE `bigtree_page_revisions` ADD COLUMN `resource_allocation` TEXT NOT NULL AFTER `saved_description`");
	SQL::query("ALTER TABLE `bigtree_page_revisions` ADD COLUMN `has_deleted_resources` CHAR(2) NOT NULL AFTER `resource_allocation`");

	// Update allocations from module to table based
	$allocations = SQL::fetchAll("SELECT * FROM bigtree_resource_allocation");

	foreach ($allocations as $alloc) {
		if ($alloc["table"] == "pages" || $alloc["table"] == "settings") {
			SQL::update("bigtree_resource_allocation", $alloc["id"], ["table" => "bigtree_".$alloc["table"]]);
		} else {
			$class_name = SQL::fetch("SELECT `class` FROM bigtree_modules WHERE id = ?", $alloc["table"]);

			if ($class_name && class_exists($class_name)) {
				$module = new $class_name;
				SQL::update("bigtree_resource_allocation", $alloc["id"], ["table" => $module->Table]);
			}
		}
	}

	$admin->updateInternalSettingValue("bigtree-internal-revision", 312);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 13"
	]);
	