<?
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	while ($current_revision < BIGTREE_REVISION) {
		$current_revision++;
		if (function_exists("_local_bigtree_update_".$current_revision)) {
			call_user_func("_local_bigtree_update_$current_revision");
		}
	}
	$admin->updateSettingValue("bigtree-internal-revision",BIGTREE_REVISION);
?>
<div class="container">
	<section>
		<p>BigTree has been updated to <?=BIGTREE_VERSION?>.</p>
	</section>
</div>
<?
	// BigTree 4.0b5 update -- REVISION 1
	function _local_bigtree_update_1() {
		global $cms,$admin;

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
	}

	// BigTree 4.0b7 update -- REVISION 5
	function _local_bigtree_update_5() {
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
	}

	// BigTree 4.0b7 update -- REVISION 6
	function _local_bigtree_update_6() {
		// Allows null values for module groups and resource folders.
		sqlquery("ALTER TABLE `bigtree_modules` CHANGE `group` `group` int(11) UNSIGNED DEFAULT NULL");
		sqlquery("ALTER TABLE `bigtree_resources` CHANGE `folder` `folder` int(11) UNSIGNED DEFAULT NULL");
	}

	// BigTree 4.0RC1 update -- REVISION 7
	function _local_bigtree_update_7() {
		// Allow forms to set their return view manually.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_view` INT(11) UNSIGNED AFTER `default_position`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 8
	function _local_bigtree_update_8() {
		// Remove image an description columns from modules.
		sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `image`");
		sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `description`");
		/// Remove locked column from pages.
		sqlquery("ALTER TABLE `bigtree_pages` DROP COLUMN `locked`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 9
	function _local_bigtree_update_9() {
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
	}
	
	// BigTree 4.0RC2 update -- REVISION 10
	function _local_bigtree_update_10() {
		sqlquery("ALTER TABLE `bigtree_modules` ADD COLUMN `icon` VARCHAR(255) NOT NULL AFTER `class`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 11
	function _local_bigtree_update_11() {
		// Got rid of the dropdown for Modules.
		sqlquery("ALTER TABLE `bigtree_module_groups` DROP COLUMN `in_nav`");
		// New Analytics stuff requires that we redo everything.
		sqlquery("UPDATE `bigtree_settings` SET value = '' WHERE id = 'bigtree-internal-google-analytics'");
	}

	// BigTree 4.0RC2 update -- REVISION 12
	function _local_bigtree_update_12() {
		// Add the return_url column to bigtree_module_forms.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_url` VARCHAR(255) NOT NULL AFTER `return_view`");
	}

	// BigTree 4.0RC2 update -- REVISION 13
	function _local_bigtree_update_13() {
		// Delete the "package" column from templates.
		sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `package`");
	}

	// BigTree 4.0RC2 update -- REVISION 14
	function _local_bigtree_update_14() {
		// Allow NULL as an option for the item_id in bigtree_pending_changes
		sqlquery("ALTER TABLE `bigtree_pending_changes` CHANGE `item_id` `item_id` INT(11) UNSIGNED DEFAULT NULL");
		// Fix anything that had a 0 before as the item_id and wasn't pages.
		sqlquery("UPDATE `bigtree_pending_changes` SET item_id = NULL WHERE item_id = 0 AND `table` != 'bigtree_pages'");
	}

	// BigTree 4.0RC2 update -- REVISION 15
	function _local_bigtree_update_15() {
		// Adds the setting to disable tagging in pages
		global $admin;
		$admin->createSetting(array("id" => "bigtree-internal-disable-page-tagging", "type" => "checkbox", "name" => "Disable Tags in Pages"));
		// Adds a column to module forms to disable tagging.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `tagging` CHAR(2) NOT NULL AFTER `return_url`");
		// Default to tagging being on since it wasn't an option to turn it off previously.
		sqlquery("UPDATE `bigtree_module_forms` SET `tagging` = 'on'");
	}

	// BigTree 4.0RC2 update -- REVISION 16
	function _local_bigtree_update_16() {
		// Adds a sort column to the view cache
		sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `sort_field` VARCHAR(255) NOT NULL AFTER `group_field`");
		// Force all the views to update their cache.
		sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");
	}

	// BigTree 4.0RC2 update -- REVISION 18
	function _local_bigtree_update_18() {
		// Adds a sort column to the view cache
		sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `published_gbp_field` TEXT NOT NULL AFTER `gbp_field`");
		// Force all the views to update their cache.
		sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");
	}

	// BigTree 4.0RC3 update -- REVISION 19
	function _local_bigtree_update_19() {
		// Add the new caches table
		sqlquery("CREATE TABLE `bigtree_caches` (`identifier` varchar(255) NOT NULL DEFAULT '', `key` varchar(255) NOT NULL DEFAULT '', `value` longtext, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}

	// BigTree 4.0 update -- REVISION 20
	function _local_bigtree_update_20() {
		// Replace "menu" types with Array of Items
		$options = sqlescape('{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"link","title":"URL (include http://)","type":"text"}]}');
		sqlquery("UPDATE `bigtree_settings` SET `type` = 'array', `options` = '$options' WHERE `type` = 'menu'");

		// Replace "many_to_many" with "many-to-many"
		$mtm_find = sqlescape('"type":"many_to_many"');
		$mtm_replace = sqlescape('"type":"many-to-many"');
		sqlquery("UPDATE `bigtree_module_forms` SET `fields` = REPLACE(`fields`,'$mtm_find','$mtm_replace')");
	}

	// BigTree 4.0 update -- REVISION 21
	function _local_bigtree_update_21() {
		global $bigtree;
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
	}

	// BigTree 4.0.1 update -- REVISION 22
	function _local_bigtree_update_22() {
		global $admin;

		// Go through all views and figure out what kind of data is in each column.
		$q = sqlquery("SELECT id FROM bigtree_module_views");
		while ($f = sqlfetch($q)) {
			$admin->updateModuleViewColumnNumericStatus(BigTreeAutoModule::getView($f["id"]));
		}
	}

	// BigTree 4.1 update -- REVISION 100 (incrementing x00 digit for a .1 release)
	function _local_bigtree_update_100() {
		global $cms;

		// Turn off foreign keys for the update
		sqlquery("SET SESSION foreign_key_checks = 0");

		// MD5 for not duplicating resources that are already uploaded, allocation table for tracking resource usage
		sqlquery("ALTER TABLE `bigtree_resources` ADD COLUMN `md5` VARCHAR(255) NOT NULL AFTER `file`");
		sqlquery("CREATE TABLE `bigtree_resource_allocation` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` varchar(255) DEFAULT NULL, `entry` varchar(255) DEFAULT NULL, `resource` int(11) unsigned DEFAULT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `resource` (`resource`), KEY `updated_at` (`updated_at`), CONSTRAINT `bigtree_resource_allocation_ibfk_1` FOREIGN KEY (`resource`) REFERENCES `bigtree_resources` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
		// SEO Invisible for passing X-Robots headers
		sqlquery("ALTER TABLE `bigtree_pages` ADD COLUMN `seo_invisible` CHAR(2) NOT NULL AFTER `meta_description`");
		// Per Page Setting
		sqlquery("INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `options`, `name`, `description`, `locked`, `system`, `encrypted`) VALUES ('bigtree-internal-per-page', X'3135', 'text', '', 'Number of Items Per Page', '<p>This should be a numeric amount and controls the number of items per page in areas such as views, settings, users, etc.</p>', 'on', '', '')");
		// Module reports
		sqlquery("CREATE TABLE `bigtree_module_reports` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL DEFAULT '', `table` varchar(255) NOT NULL, `type` varchar(255) NOT NULL, `filters` text NOT NULL, `fields` text NOT NULL, `parser` varchar(255) NOT NULL DEFAULT '', `view` int(11) unsigned DEFAULT NULL, PRIMARY KEY (`id`), KEY `view` (`view`), CONSTRAINT `bigtree_module_reports_ibfk_1` FOREIGN KEY (`view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		sqlquery("ALTER TABLE `bigtree_module_actions` ADD COLUMN `report` int(11) unsigned NULL AFTER `view`");
		// Embeddable Module Forms
		sqlquery("CREATE TABLE `bigtree_module_embeds` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL, `preprocess` varchar(255) NOT NULL, `callback` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `fields` text NOT NULL, `default_position` varchar(255) NOT NULL, `default_pending` char(2) NOT NULL, `css` varchar(255) NOT NULL, `hash` varchar(255) NOT NULL DEFAULT '', `redirect_url` varchar(255) NOT NULL DEFAULT '', `thank_you_message` text NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		// Callout groups
		sqlquery("CREATE TABLE `bigtree_callout_groups` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		sqlquery("ALTER TABLE `bigtree_callouts` ADD COLUMN `group` int(11) unsigned NULL AFTER `position`");
		// Create the extensions table (packages for now, 4.2 prep for extensions)
		sqlquery("CREATE TABLE `bigtree_extensions` (`id` varchar(255) NOT NULL DEFAULT '', `type` varchar(255) DEFAULT NULL, `name` varchar(255) DEFAULT NULL, `version` varchar(255) DEFAULT NULL, `last_updated` datetime DEFAULT NULL, `manifest` LONGTEXT DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

		// Get all templates with callouts enabled and provide them with a new resource instead
		$tq = sqlquery("SELECT * FROM bigtree_templates WHERE callouts_enabled = 'on'");
		while ($template = sqlfetch($tq)) {
			$resources = json_decode($template["resources"],true);
			// See if we have a "callouts" resource already
			$found = false;
			foreach ($resources as $r) {
				if ($r["id"] == "callouts") {
					$found = true;
				}
			}
			// If we already have callouts, use 4.0-callouts
			if ($found) {
				$resources[] = array("id" => "4.0-callouts","title" => "Callouts","type" => "callouts");
			} else {
				$resources[] = array("id" => "callouts","title" => "Callouts","type" => "callouts");
			}
			$resources = sqlescape(json_encode($resources));
			sqlquery("UPDATE bigtree_templates SET resources = '$resources' WHERE id = '".sqlescape($template["id"])."'");
	
			// Find pages that use this template
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE template = '".sqlescape($template["id"])."' AND callouts != '[]'");
			while ($f = sqlfetch($q)) {
				$resources = json_decode($f["resources"],true);
				$callouts = json_decode($f["callouts"],true);
				if ($found) {
					$resources["4.0-callouts"] = $callouts;
				} else {
					$resources["callouts"] = $callouts;
				}
				$resources = sqlescape(json_encode($resources));
				sqlquery("UPDATE bigtree_pages SET resources = '$resources' WHERE id = '".$f["id"]."'");
			}
		}

		// Switch storage settings
		$storage_settings = $cms->getSetting("bigtree-internal-storage");
		if ($storage_settings["service"] == "s3") {
			$cloud = new BigTreeCloudStorage;
			$cloud->Settings["amazon"] = array("key" => $storage_settings["s3"]["keys"]["access_key_id"],"secret" => $storage_settings["s3"]["keys"]["secret_access_key"]);
			unset($cloud);
		} elseif ($storage_settings["service"] == "rackspace") {
			$cloud = new BigTreeCloudStorage;
			$cloud->Settings["rackspace"] = array("api_key" => $storage_settings["rackspace"]["keys"]["api_key"],"username" => $storage_settings["rackspace"]["keys"]["username"]);
			unset($cloud);
		}
		sqlquery("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-storage'");

		// Adjust module relationships better so that we can just delete a module and have everything cascade delete
		sqlquery("ALTER TABLE bigtree_module_forms ADD COLUMN `module` INT(11) unsigned AFTER `id`");
		sqlquery("ALTER TABLE bigtree_module_forms ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		sqlquery("ALTER TABLE bigtree_module_reports ADD COLUMN `module` INT(11) unsigned AFTER `id`");
		sqlquery("ALTER TABLE bigtree_module_reports ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		sqlquery("ALTER TABLE bigtree_module_views ADD COLUMN `module` INT(11) unsigned AFTER `id`");
		sqlquery("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		sqlquery("ALTER TABLE bigtree_module_embeds ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		// Find all the relevant forms / views / reports and assign them to their proper module.
		$q = sqlquery("SELECT * FROM bigtree_module_actions");
		while ($f = sqlfetch($q)) {
			sqlquery("UPDATE bigtree_module_forms SET module = '".$f["module"]."' WHERE id = '".$f["form"]."'");
			sqlquery("UPDATE bigtree_module_reports SET module = '".$f["module"]."' WHERE id = '".$f["report"]."'");
			sqlquery("UPDATE bigtree_module_views SET module = '".$f["module"]."' WHERE id = '".$f["view"]."'");
		}

		// Adjust Module Views to use a related form instead of handling suffix tracking
		sqlquery("ALTER TABLE bigtree_module_views ADD COLUMN `related_form` INT(11) unsigned");
		sqlquery("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (related_form) REFERENCES `bigtree_module_forms` (id) ON DELETE SET NULL");

		$q = sqlquery("SELECT * FROM bigtree_module_views WHERE suffix != ''");
		while ($f = sqlfetch($q)) {
			$suffix = sqlescape($f["suffix"]);
			// Find the related form
			$form = sqlfetch(sqlquery("SELECT form FROM bigtree_module_actions WHERE module = '".$f["module"]."' AND (route = 'add-$suffix' OR route = 'edit-$suffix')"));
			if ($form["id"]) {
				sqlquery("UPDATE bigtree_module_views SET related_form = '".$form["id"]."' WHERE id = '".$f["id"]."'");
			}
		}

		// Unused columns
		sqlquery("ALTER TABLE `bigtree_module_forms` DROP COLUMN `positioning`");
		sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `image`");
		sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `callouts_enabled`");
		sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `description`");
		sqlquery("ALTER TABLE `bigtree_pages` DROP COLUMN `callouts`");
		sqlquery("ALTER TABLE `bigtree_page_revisions` DROP COLUMN `callouts`");
		sqlquery("ALTER TABLE `bigtree_module_views` DROP COLUMN `suffix`");

		// Reinstate foreign keys
		sqlquery("SET SESSION foreign_key_checks = 1");
	}

	// BigTree 4.1.1 update -- REVISION 101
	function _local_bigtree_update_101() {
		sqlquery("ALTER TABLE bigtree_caches CHANGE `key` `key` VARCHAR(10000)");
		$storage = new BigTreeStorage;
		if (is_array($storage->Settings->Files)) {
			foreach ($storage->Settings->Files as $file) {
				sqlquery("INSERT INTO bigtree_caches (`identifier`,`key`,`value`) VALUES ('org.bigtreecms.cloudfiles','".sqlescape($file["path"])."','".sqlescape(json_encode($file))."')");
			}
		}
		unset($storage->Settings->Files);
	}

	// BigTree 4.1.1 update -- REVISION 102
	function _local_bigtree_update_102() {
		sqlquery("ALTER TABLE bigtree_field_types ADD COLUMN `use_cases` TEXT NOT NULL AFTER `name`");
		sqlquery("ALTER TABLE bigtree_field_types ADD COLUMN `self_draw` CHAR(2) NULL AFTER `use_cases`");
		$q = sqlquery("SELECT * FROM bigtree_field_types");
		while ($f = sqlfetch($q)) {
			$use_cases = sqlescape(json_encode(array(
				"templates" => $f["pages"],
				"modules" => $f["modules"],
				"callouts" => $f["callouts"],
				"settings" => $f["settings"]
			)));
			sqlquery("UPDATE bigtree_field_types SET use_cases = '$use_cases' WHERE id = '".sqlescape($f["id"])."'");
		}
		sqlquery("ALTER TABLE bigtree_field_types DROP `pages`, DROP `modules`, DROP `callouts`, DROP `settings`");
	}

	// BigTree 4.1.1 update -- REVISION 103
	function _local_bigtree_update_103() {
		global $cms;
		// Converting resource thumbnail sizes to a properly editable feature and naming it better.
		$current = $cms->getSetting("resource-thumbnail-sizes");
		$thumbs = json_decode($current,true);
		$value = array();
		foreach ($thumbs as $title => $info) {
			$value[] = array("title" => $title,"prefix" => $info["prefix"],"width" => $info["width"],"height" => $info["height"]);
		}
		sqlquery("INSERT INTO bigtree_settings (`id`,`value`,`type`,`options`,`name`,`locked`) VALUES ('bigtree-file-manager-thumbnail-sizes','".sqlescape(json_encode($value))."','array','".sqlescape('{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"prefix","title":"File Prefix (i.e. thumb_)","type":"text"},{"key":"width","title":"Width","type":"text"},{"key":"height","title":"Height","type":"text"}]}')."','File Manager Thumbnail Sizes','on')");
		sqlquery("DELETE FROM bigtree_settings WHERE id = 'resource-thumbnail-sizes'");
	}
?>