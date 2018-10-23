<?php
	// BigTree 4.0 beta -> 4.0 release

	// Update settings to make the value LONGTEXT
	sqlquery("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGTEXT");

	// Drop the css/javascript columns from bigtree_module_forms and add preprocess
	sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `preprocess` varchar(255) NOT NULL AFTER `title`, DROP COLUMN `javascript`, DROP COLUMN `css`");

	// Add the "trunk" column to bigtree_pages
	sqlquery("ALTER TABLE `bigtree_pages` ADD COLUMN `trunk` char(2) NOT NULL AFTER `id`");
	sqlquery("UPDATE `bigtree_pages` SET `trunk` = 'on' WHERE id = '0'");

	// Move Google Analytics information into a single setting
	$ga_email = $cms->getSetting("bigtree-internal-google-analytics-email");
	$ga_password = $cms->getSetting("bigtree-internal-google-analytics-password");
	$ga_profile = $cms->getSetting("bigtree-internal-google-analytics-profile");

	$admin->createSetting(array(
		"id" => "bigtree-internal-google-analytics",
		"system" => "on",
		"encrypted" => "on"
	));
	$admin->updateSettingValue("bigtree-internal-google-analytics",array(
		"email" => $ga_email,
		"password" => $ga_password,
		"profile" => $ga_profile
	));


	// Update the upload service setting to be encrypted.
	$admin->updateSetting("bigtree-internal-upload-service",array(
		"id" => "bigtree-internal-upload-service",
		"system" => "on",
		"encrypted" => "on"
	));
	$us = $cms->getSetting("bigtree-internal-upload-service");

	// Move Rackspace into the main upload service
	$rs_containers = $cms->getSetting("bigtree-internal-rackspace-containers");
	$rs_keys = $cms->getSetting("bigtree-internal-rackspace-containers");

	$us["rackspace"] = array(
		"containers" => $rs_containers,
		"keys" => $rs_keys
	);

	// Move Amazon S3 into the main upload service
	$s3_buckets = $cms->getSetting("bigtree-internal-s3-buckets");
	$s3_keys = $cms->getSetting("bigtree-internal-s3-keys");

	$us["s3"] = array(
		"buckets" => $s3_buckets,
		"keys" => $s3_keys
	);

	// Update the upload service value.
	$admin->updateSettingValue("bigtree-internal-upload-service",$us);

	// Create the revision counter
	$admin->createSetting(array(
		"id" => "bigtree-internal-revision",
		"system" => "on"
	));

	// Delete all the old settings.
	sqlquery("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-google-analytics-email' OR id = 'bigtree-internal-google-analytics-password' OR id = 'bigtree-internal-google-analytics-profile' OR id = 'bigtree-internal-rackspace-keys' OR id = 'bigtree-internal-rackspace-containers' OR id = 'bigtree-internal-s3-buckets' OR id = 'bigtree-internal-s3-keys'");

	// Fixes AES_ENCRYPT not encoding things properly.
	sqlquery("ALTER TABLE `bigtree_settings` CHANGE `value` `value` longblob NOT NULL");

	// Adds the ability to make a field type available for Settings.
	sqlquery("ALTER TABLE `bigtree_field_types` ADD COLUMN `settings` char(2) NOT NULL AFTER `callouts`");

	// Remove uncached.
	sqlquery("ALTER TABLE `bigtree_module_views` DROP COLUMN `uncached`");

	// Adds the ability to set options on a setting.
	sqlquery("ALTER TABLE `bigtree_settings` ADD COLUMN `options` text NOT NULL AFTER `type`");

	// Alter the module view cache table so that it can be used for custom view caching
	sqlquery("ALTER TABLE `bigtree_module_view_cache` CHANGE `view` `view` varchar(255) NOT NULL");

	// Allows null values for module groups and resource folders.
	sqlquery("ALTER TABLE `bigtree_modules` CHANGE `group` `group` int(11) UNSIGNED DEFAULT NULL");
	sqlquery("ALTER TABLE `bigtree_resources` CHANGE `folder` `folder` int(11) UNSIGNED DEFAULT NULL");

	// Allow forms to set their return view manually.
	sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_view` INT(11) UNSIGNED AFTER `default_position`");

	// Remove image an description columns from modules.
	sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `image`");
	sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `description`");
	/// Remove locked column from pages.
	sqlquery("ALTER TABLE `bigtree_pages` DROP COLUMN `locked`");

	sqlquery("ALTER TABLE `bigtree_tags_rel` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `module`");

	// Figure out the table for all the modules and change the tags to be related to the table instead of the module.
	$q = sqlquery("SELECT * FROM bigtree_modules");
	
	while ($f = sqlfetch($q)) {
		if (class_exists($f["class"])) {
			$test = new $f["class"];
			$table = sqlescape($test->Table);
			sqlquery("UPDATE `bigtree_tags_rel` SET `table` = '$table' WHERE module = '".$f["id"]."'");
		}
	}

	sqlquery("UPDATE `bigtree_tags_rel` SET `table` = 'bigtree_pages' WHERE module = 0");

	// And drop the module column.
	sqlquery("ALTER TABLE `bigtree_tags_rel` DROP COLUMN `module`");
	sqlquery("ALTER TABLE `bigtree_modules` ADD COLUMN `icon` VARCHAR(255) NOT NULL AFTER `class`");
	
	// Got rid of the dropdown for Modules.
	sqlquery("ALTER TABLE `bigtree_module_groups` DROP COLUMN `in_nav`");

	// New Analytics stuff requires that we redo everything.
	sqlquery("UPDATE `bigtree_settings` SET value = '' WHERE id = 'bigtree-internal-google-analytics'");
	
	// Add the return_url column to bigtree_module_forms.
	sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_url` VARCHAR(255) NOT NULL AFTER `return_view`");
	
	// Delete the "package" column from templates.
	sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `package`");
	
	// Allow NULL as an option for the item_id in bigtree_pending_changes
	sqlquery("ALTER TABLE `bigtree_pending_changes` CHANGE `item_id` `item_id` INT(11) UNSIGNED DEFAULT NULL");
	// Fix anything that had a 0 before as the item_id and wasn't pages.
	sqlquery("UPDATE `bigtree_pending_changes` SET item_id = NULL WHERE item_id = 0 AND `table` != 'bigtree_pages'");
	
	// Adds the setting to disable tagging in pages
	$admin->createSetting(array("id" => "bigtree-internal-disable-page-tagging", "type" => "checkbox", "name" => "Disable Tags in Pages"));
	// Adds a column to module forms to disable tagging.
	sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `tagging` CHAR(2) NOT NULL AFTER `return_url`");
	// Default to tagging being on since it wasn't an option to turn it off previously.
	sqlquery("UPDATE `bigtree_module_forms` SET `tagging` = 'on'");
	
	// Adds a sort column to the view cache
	sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `sort_field` VARCHAR(255) NOT NULL AFTER `group_field`");
	// Force all the views to update their cache.
	sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");

	// Adds a sort column to the view cache
	sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `published_gbp_field` TEXT NOT NULL AFTER `gbp_field`");
	// Force all the views to update their cache.
	sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");

	// Add the new caches table
	sqlquery("CREATE TABLE `bigtree_caches` (`identifier` varchar(255) NOT NULL DEFAULT '', `key` varchar(255) NOT NULL DEFAULT '', `value` longtext, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	
	// Replace "menu" types with Array of Items
	$options = sqlescape('{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"link","title":"URL (include http://)","type":"text"}]}');
	sqlquery("UPDATE `bigtree_settings` SET `type` = 'array', `options` = '$options' WHERE `type` = 'menu'");

	// Replace "many_to_many" with "many-to-many"
	$mtm_find = sqlescape('"type":"many_to_many"');
	$mtm_replace = sqlescape('"type":"many-to-many"');
	sqlquery("UPDATE `bigtree_module_forms` SET `fields` = REPLACE(`fields`,'$mtm_find','$mtm_replace')");
	
	// Fix widths on module view actions
	$q = sqlquery("SELECT * FROM bigtree_module_views");

	while ($f = sqlfetch($q)) {
		$actions = json_decode($f["actions"],true);
		$extra_width = count($actions) * 22; // From 62px to 40px per action.
		$fields = json_decode($f["fields"],true);
	
		foreach ($fields as &$field) {
			if ($field["width"]) {
				$field["width"] += floor($extra_width / count($fields));
			}
		}
	
		$fields = sqlescape(json_encode($fields));
		sqlquery("UPDATE bigtree_module_views SET `fields` = '$fields' WHERE id = '".$f["id"]."'");
	}
	
	$admin->updateSettingValue("bigtree-internal-revision", 21);
