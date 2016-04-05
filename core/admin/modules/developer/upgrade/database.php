<?php
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
<?php
	// BigTree 4.0b5 update -- REVISION 1
	function _local_bigtree_update_1() {
		global $admin,$cms,$db;

		// Update settings to make the value LONGTEXT
		$db->query("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGTEXT");

		// Drop the css/javascript columns from bigtree_module_forms and add preprocess
		$db->query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `preprocess` varchar(255) NOT NULL AFTER `title`, DROP COLUMN `javascript`, DROP COLUMN `css`");

		// Add the "trunk" column to bigtree_pages
		$db->query("ALTER TABLE `bigtree_pages` ADD COLUMN `trunk` char(2) NOT NULL AFTER `id`");
		$db->update("bigtree_pages",0,array("trunk" => "on"));

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
		$db->query("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-google-analytics-email' OR id = 'bigtree-internal-google-analytics-password' OR id = 'bigtree-internal-google-analytics-profile' OR id = 'bigtree-internal-rackspace-keys' OR id = 'bigtree-internal-rackspace-containers' OR id = 'bigtree-internal-s3-buckets' OR id = 'bigtree-internal-s3-keys'");
	}

	// BigTree 4.0b7 update -- REVISION 5
	function _local_bigtree_update_5() {
		global $db;

		// Fixes AES_ENCRYPT not encoding things properly.
		$db->query("ALTER TABLE `bigtree_settings` CHANGE `value` `value` longblob NOT NULL");

		// Adds the ability to make a field type available for Settings.
		$db->query("ALTER TABLE `bigtree_field_types` ADD COLUMN `settings` char(2) NOT NULL AFTER `callouts`");

		// Remove uncached.
		$db->query("ALTER TABLE `bigtree_module_views` DROP COLUMN `uncached`");

		// Adds the ability to set options on a setting.
		$db->query("ALTER TABLE `bigtree_settings` ADD COLUMN `options` text NOT NULL AFTER `type`");

		// Alter the module view cache table so that it can be used for custom view caching
		$db->query("ALTER TABLE `bigtree_module_view_cache` CHANGE `view` `view` varchar(255) NOT NULL");
	}

	// BigTree 4.0b7 update -- REVISION 6
	function _local_bigtree_update_6() {
		global $db;

		// Allows null values for module groups and resource folders.
		$db->query("ALTER TABLE `bigtree_modules` CHANGE `group` `group` int(11) UNSIGNED DEFAULT NULL");
		$db->query("ALTER TABLE `bigtree_resources` CHANGE `folder` `folder` int(11) UNSIGNED DEFAULT NULL");
	}

	// BigTree 4.0RC1 update -- REVISION 7
	function _local_bigtree_update_7() {
		global $db;

		// Allow forms to set their return view manually.
		$db->query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_view` INT(11) UNSIGNED AFTER `default_position`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 8
	function _local_bigtree_update_8() {
		global $db;

		// Remove image an description columns from modules.
		$db->query("ALTER TABLE `bigtree_modules` DROP COLUMN `image`");
		$db->query("ALTER TABLE `bigtree_modules` DROP COLUMN `description`");
		/// Remove locked column from pages.
		$db->query("ALTER TABLE `bigtree_pages` DROP COLUMN `locked`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 9
	function _local_bigtree_update_9() {
		global $db;

		$db->query("ALTER TABLE `bigtree_tags_rel` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `module`");

		// Figure out the table for all the modules and change the tags to be related to the table instead of the module.
		$q = $db->query("SELECT * FROM bigtree_modules");
		while ($f = $q->fetch()) {
			if (class_exists($f["class"])) {
				$test = new $f["class"];
				$db->update("bigtree_tags_rel", array("module" => $f["id"]), array("table" => $test->Table));
			}
		}
		$db->update("bigtree_tags_rel", array("module" => 0), array("table" => "bigtree_pages"));

		// And drop the module column.
		$db->query("ALTER TABLE `bigtree_tags_rel` DROP COLUMN `module`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 10
	function _local_bigtree_update_10() {
		global $db;

		$db->query("ALTER TABLE `bigtree_modules` ADD COLUMN `icon` VARCHAR(255) NOT NULL AFTER `class`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 11
	function _local_bigtree_update_11() {
		global $db;

		// Got rid of the dropdown for Modules.
		$db->query("ALTER TABLE `bigtree_module_groups` DROP COLUMN `in_nav`");
		// New Analytics stuff requires that we redo everything.
		$db->update("bigtree_settings", "bigtree-internal-google-analytics", array("value" => ""));
	}

	// BigTree 4.0RC2 update -- REVISION 12
	function _local_bigtree_update_12() {
		global $db;

		// Add the return_url column to bigtree_module_forms.
		$db->query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_url` VARCHAR(255) NOT NULL AFTER `return_view`");
	}

	// BigTree 4.0RC2 update -- REVISION 13
	function _local_bigtree_update_13() {
		global $db;
		
		// Delete the "package" column from templates.
		$db->query("ALTER TABLE `bigtree_templates` DROP COLUMN `package`");
	}

	// BigTree 4.0RC2 update -- REVISION 14
	function _local_bigtree_update_14() {
		global $db;
		
		// Allow NULL as an option for the item_id in bigtree_pending_changes
		$db->query("ALTER TABLE `bigtree_pending_changes` CHANGE `item_id` `item_id` INT(11) UNSIGNED DEFAULT NULL");
		
		// Fix anything that had a 0 before as the item_id and wasn't pages.
		$db->query("UPDATE `bigtree_pending_changes` SET item_id = NULL WHERE item_id = 0 AND `table` != 'bigtree_pages'");
	}

	// BigTree 4.0RC2 update -- REVISION 15
	function _local_bigtree_update_15() {
		global $admin,$db;
		
		// Adds the setting to disable tagging in pages
		$admin->createSetting(array("id" => "bigtree-internal-disable-page-tagging", "type" => "checkbox", "name" => "Disable Tags in Pages"));

		// Adds a column to module forms to disable tagging.
		$db->query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `tagging` CHAR(2) NOT NULL AFTER `return_url`");

		// Default to tagging being on since it wasn't an option to turn it off previously.
		$db->query("UPDATE `bigtree_module_forms` SET `tagging` = 'on'");
	}

	// BigTree 4.0RC2 update -- REVISION 16
	function _local_bigtree_update_16() {
		global $db;
		
		// Adds a sort column to the view cache
		$db->query("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `sort_field` VARCHAR(255) NOT NULL AFTER `group_field`");
		
		// Force all the views to update their cache.
		$db->query("TRUNCATE TABLE `bigtree_module_view_cache`");
	}

	// BigTree 4.0RC2 update -- REVISION 18
	function _local_bigtree_update_18() {
		global $db;
		
		// Adds a sort column to the view cache
		$db->query("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `published_gbp_field` TEXT NOT NULL AFTER `gbp_field`");
		
		// Force all the views to update their cache.
		$db->query("TRUNCATE TABLE `bigtree_module_view_cache`");
	}

	// BigTree 4.0RC3 update -- REVISION 19
	function _local_bigtree_update_19() {
		global $db;
		
		// Add the new caches table
		$db->query("CREATE TABLE `bigtree_caches` (`identifier` varchar(255) NOT NULL DEFAULT '', `key` varchar(255) NOT NULL DEFAULT '', `value` longtext, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}

	// BigTree 4.0 update -- REVISION 20
	function _local_bigtree_update_20() {
		global $db;
		
		// Replace "menu" types with Array of Items
		$options = '{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"link","title":"URL (include http://)","type":"text"}]}';
		$db->update("bigtree_settings", array("type" => "menu"), array("type" => "array", "options" => $options));

		// Replace "many_to_many" with "many-to-many"
		$mtm_find = $db->escape('"type":"many_to_many"');
		$mtm_replace = $db->escape('"type":"many-to-many"');
		$db->query("UPDATE `bigtree_module_forms` SET `fields` = REPLACE(`fields`,'$mtm_find','$mtm_replace')");
	}

	// BigTree 4.0 update -- REVISION 21
	function _local_bigtree_update_21() {
		global $bigtree, $db;

		// Fix widths on module view actions
		$q = $db->query("SELECT * FROM bigtree_module_views");
		while ($f = $q->fetch()) {
			$actions = json_decode($f["actions"],true);
			$extra_width = count($actions) * 22; // From 62px to 40px per action.
			$fields = json_decode($f["fields"],true);
			foreach ($fields as &$field) {
				if ($field["width"]) {
					$field["width"] += floor($extra_width / count($fields));
				}
			}
			$db->update("bigtree_module_views", $f["id"], array("fields" => $fields));
		}
	}

	// BigTree 4.0.1 update -- REVISION 22
	function _local_bigtree_update_22() {
		global $admin, $db;

		// Go through all views and figure out what kind of data is in each column.
		$view_ids = $db->fetchAllSingle("SELECT id FROM bigtree_module_views");
		foreach ($view_ids as $id) {
			$admin->updateModuleViewColumnNumericStatus(BigTreeAutoModule::getView($id));
		}
	}

	// BigTree 4.1 update -- REVISION 100 (incrementing x00 digit for a .1 release)
	function _local_bigtree_update_100() {
		global $cms, $db;

		// Turn off foreign keys for the update
		$db->query("SET SESSION foreign_key_checks = 0");

		// MD5 for not duplicating resources that are already uploaded, allocation table for tracking resource usage
		$db->query("ALTER TABLE `bigtree_resources` ADD COLUMN `md5` VARCHAR(255) NOT NULL AFTER `file`");
		$db->query("CREATE TABLE `bigtree_resource_allocation` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` varchar(255) DEFAULT NULL, `entry` varchar(255) DEFAULT NULL, `resource` int(11) unsigned DEFAULT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `resource` (`resource`), KEY `updated_at` (`updated_at`), CONSTRAINT `bigtree_resource_allocation_ibfk_1` FOREIGN KEY (`resource`) REFERENCES `bigtree_resources` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
		
		// SEO Invisible for passing X-Robots headers
		$db->query("ALTER TABLE `bigtree_pages` ADD COLUMN `seo_invisible` CHAR(2) NOT NULL AFTER `meta_description`");
		
		// Per Page Setting
		$db->query("INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `options`, `name`, `description`, `locked`, `system`, `encrypted`) VALUES ('bigtree-internal-per-page', X'3135', 'text', '', 'Number of Items Per Page', '<p>This should be a numeric amount and controls the number of items per page in areas such as views, settings, users, etc.</p>', 'on', '', '')");

		// Module reports
		$db->query("CREATE TABLE `bigtree_module_reports` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL DEFAULT '', `table` varchar(255) NOT NULL, `type` varchar(255) NOT NULL, `filters` text NOT NULL, `fields` text NOT NULL, `parser` varchar(255) NOT NULL DEFAULT '', `view` int(11) unsigned DEFAULT NULL, PRIMARY KEY (`id`), KEY `view` (`view`), KEY `module` (`module`), CONSTRAINT `bigtree_module_reports_ibfk_2` FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE, CONSTRAINT `bigtree_module_reports_ibfk_1` FOREIGN KEY (`view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$db->query("ALTER TABLE `bigtree_module_actions` ADD COLUMN `report` int(11) unsigned NULL AFTER `view`");
		
		// Embeddable Module Forms
		$db->query("CREATE TABLE `bigtree_module_embeds` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL, `preprocess` varchar(255) NOT NULL, `callback` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `fields` text NOT NULL, `default_position` varchar(255) NOT NULL, `default_pending` char(2) NOT NULL, `css` varchar(255) NOT NULL, `hash` varchar(255) NOT NULL DEFAULT '', `redirect_url` varchar(255) NOT NULL DEFAULT '', `thank_you_message` text NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		
		// Callout groups
		$db->query("CREATE TABLE `bigtree_callout_groups` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$db->query("ALTER TABLE `bigtree_callouts` ADD COLUMN `group` int(11) unsigned NULL AFTER `position`");
		
		// Create the extensions table (packages for now, 4.2 prep for extensions)
		$db->query("CREATE TABLE `bigtree_extensions` (`id` varchar(255) NOT NULL DEFAULT '', `type` varchar(255) DEFAULT NULL, `name` varchar(255) DEFAULT NULL, `version` varchar(255) DEFAULT NULL, `last_updated` datetime DEFAULT NULL, `manifest` LONGTEXT DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

		// Get all templates with callouts enabled and provide them with a new resource instead
		$tq = $db->query("SELECT * FROM bigtree_templates WHERE callouts_enabled = 'on'");
		while ($template = $tq->fetch()) {
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

			$db->update("bigtree_templates", $template["id"], array("resources" => $resources));
	
			// Find pages that use this template
			$q = $db->query("SELECT * FROM bigtree_pages WHERE template = ? AND callouts != '[]'", $template["id"]);
			while ($f = $q->fetch()) {
				$resources = json_decode($f["resources"],true);
				$callouts = json_decode($f["callouts"],true);
				if ($found) {
					$resources["4.0-callouts"] = $callouts;
				} else {
					$resources["callouts"] = $callouts;
				}

				$db->update("bigtree_pages", $f["id"], array("resources" => $resources));
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
		$db->delete("bigtree_settings", "bigtree-internal-storage");

		// Adjust module relationships better so that we can just delete a module and have everything cascade delete
		$db->query("ALTER TABLE bigtree_module_forms ADD COLUMN `module` INT(11) unsigned AFTER `id`");
		$db->query("ALTER TABLE bigtree_module_forms ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_module_views ADD COLUMN `module` INT(11) unsigned AFTER `id`");
		$db->query("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		
		// Find all the relevant forms / views / reports and assign them to their proper module.
		$q = $db->query("SELECT * FROM bigtree_module_actions");
		while ($f = $q->fetch()) {
			$db->update("bigtree_module_forms", $f["form"], array("module" => $f["module"]));
			$db->update("bigtree_module_views", $f["view"], array("module" => $f["module"]));
		}

		// Adjust Module Views to use a related form instead of handling suffix tracking
		$db->query("ALTER TABLE bigtree_module_views ADD COLUMN `related_form` INT(11) unsigned");
		$db->query("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (related_form) REFERENCES `bigtree_module_forms` (id) ON DELETE SET NULL");

		$q = $db->query("SELECT * FROM bigtree_module_views WHERE suffix != ''");
		while ($f = $q->fetch()) {
			$suffix = $db->escape($f["suffix"]);
			// Find the related form
			$form_id = $db->fetchSingle("SELECT form FROM bigtree_module_actions WHERE module = '".$f["module"]."' AND (route = 'add-$suffix' OR route = 'edit-$suffix')");
			if ($form_id) {
				$db->update("bigtree_module_views", $f["id"], array("related_form" => $form_id));
			}
		}

		// Unused columns
		$db->query("ALTER TABLE `bigtree_module_forms` DROP COLUMN `positioning`");
		$db->query("ALTER TABLE `bigtree_templates` DROP COLUMN `image`");
		$db->query("ALTER TABLE `bigtree_templates` DROP COLUMN `callouts_enabled`");
		$db->query("ALTER TABLE `bigtree_templates` DROP COLUMN `description`");
		$db->query("ALTER TABLE `bigtree_pages` DROP COLUMN `callouts`");
		$db->query("ALTER TABLE `bigtree_page_revisions` DROP COLUMN `callouts`");
		$db->query("ALTER TABLE `bigtree_module_views` DROP COLUMN `suffix`");

		// Reinstate foreign keys
		$db->query("SET SESSION foreign_key_checks = 1");
	}

	// BigTree 4.1.1 update -- REVISION 101
	function _local_bigtree_update_101() {
		global $db;
		
		$db->query("ALTER TABLE bigtree_caches CHANGE `key` `key` VARCHAR(10000)");
		$storage = new BigTreeStorage;
		if (is_array($storage->Settings["Files"])) {
			foreach ($storage->Settings["Files"] as $file) {
				$db->insert("bigtree_caches", array(
					"identifier" => "org.bigtreecms.cloudfiles",
					"key" => $file["path"],
					"value" => $file
				));
			}
		}
		unset($storage->Settings["Files"]);
	}

	// BigTree 4.1.1 update -- REVISION 102
	function _local_bigtree_update_102() {
		global $db;
		
		$db->query("ALTER TABLE bigtree_field_types ADD COLUMN `use_cases` TEXT NOT NULL AFTER `name`");
		$db->query("ALTER TABLE bigtree_field_types ADD COLUMN `self_draw` CHAR(2) NULL AFTER `use_cases`");
		$q = $db->query("SELECT * FROM bigtree_field_types");
		while ($f = $q->fetch()) {
			$use_cases = array(
				"templates" => $f["pages"],
				"modules" => $f["modules"],
				"callouts" => $f["callouts"],
				"settings" => $f["settings"]
			);
			$db->update("bigtree_field_types", $f["id"], array("use_cases" => $use_cases));
		}
		$db->query("ALTER TABLE bigtree_field_types DROP `pages`, DROP `modules`, DROP `callouts`, DROP `settings`");
	}

	// BigTree 4.1.1 update -- REVISION 103
	function _local_bigtree_update_103() {
		global $cms, $db;

		// Converting resource thumbnail sizes to a properly editable feature and naming it better.
		$current = $cms->getSetting("resource-thumbnail-sizes");
		$thumbs = json_decode($current,true);
		$value = array();
		foreach (array_filter((array)$thumbs) as $title => $info) {
			$value[] = array("title" => $title,"prefix" => $info["prefix"],"width" => $info["width"],"height" => $info["height"]);
		}

		$db->insert("bigtree_settings", array(
			"id" => "bigtree-file-manager-thumbnail-sizes",
			"value" => $value,
			"type" => "array",
			"options" => '{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"prefix","title":"File Prefix (i.e. thumb_)","type":"text"},{"key":"width","title":"Width","type":"text"},{"key":"height","title":"Height","type":"text"}]}',
			"name" => "File Manager Thumbnail Sizes",
			"locked" => "on"
		));
		$db->delete("bigtree_settings", "resource-thumbnail-sizes");
	}

	// BigTree 4.2 update -- REVISION 200
	function _local_bigtree_update_200() {
		global $admin, $cms, $db;

		// Drop unused comments column
		$db->query("ALTER TABLE bigtree_pending_changes DROP COLUMN `comments`");

		// Add extension columns
		$db->query("ALTER TABLE bigtree_callouts ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_callouts ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_feeds ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_feeds ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_field_types ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_field_types ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_modules ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_modules ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_module_groups ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_module_groups ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_settings ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_settings ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		$db->query("ALTER TABLE bigtree_templates ADD COLUMN `extension` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_templates ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");

		// New publish_hook column, consolidate other hooks into one column
		$db->query("ALTER TABLE bigtree_pending_changes ADD COLUMN `publish_hook` VARCHAR(255)");
		$db->query("ALTER TABLE bigtree_module_forms ADD COLUMN `hooks` TEXT");
		$db->query("ALTER TABLE bigtree_module_embeds ADD COLUMN `hooks` TEXT");
		$q = $db->query("SELECT * FROM bigtree_module_forms");
		while ($f = $q->fetch()) {
			$hooks = array();
			$hooks["pre"] = $f["preprocess"];
			$hooks["post"] = $f["callback"];
			$hooks["publish"] = "";
			$db->update("bigtree_module_forms", $f["id"], array("hooks" => $hooks));
		}
		$q = $db->query("SELECT * FROM bigtree_module_embeds");
		while ($f = $q->fetch()) {
			$hooks = array();
			$hooks["pre"] = $f["preprocess"];
			$hooks["post"] = $f["callback"];
			$hooks["publish"] = "";
			$db->update("bigtree_module_embeds", $f["id"], array("hooks" => $hooks));
		}
		$db->query("ALTER TABLE bigtree_module_forms DROP COLUMN `preprocess`");
		$db->query("ALTER TABLE bigtree_module_forms DROP COLUMN `callback`");
		$db->query("ALTER TABLE bigtree_module_embeds DROP COLUMN `preprocess`");
		$db->query("ALTER TABLE bigtree_module_embeds DROP COLUMN `callback`");

		// Adjust groups/callouts for multi-support -- first we drop the foreign key
		$table_desc = BigTree::describeTable("bigtree_callouts");
		foreach ($table_desc["foreign_keys"] as $name => $definition) {
			if ($definition["local_columns"][0] === "group") {
				$db->query("ALTER TABLE bigtree_callouts DROP FOREIGN KEY `$name`");
			}
		}
		// Add the field to the groups
		$db->query("ALTER TABLE bigtree_callout_groups ADD COLUMN `callouts` TEXT AFTER `name`");
		// Find all the callouts in each group
		$q = $db->query("SELECT * FROM bigtree_callout_groups");
		while ($f = $q->fetch()) {
			$callouts = array();
			$qq = $db->query("SELECT * FROM bigtree_callouts WHERE `group` = '".$f["id"]."' ORDER BY position DESC, id ASC");
			while ($ff = $qq->fetch()) {
				$callouts[] = $ff["id"];
			}
			$db->update("bigtree_callout_groups", $f["id"], array("callouts" => $callouts));
		}
		// Drop the group column
		$db->query("ALTER TABLE bigtree_callouts DROP COLUMN `group`");

		// Security policy setting
		$db->insert("bigtree_settings", array(
			"id" => "bigtree-internal-security-policy",
			"value" => "{}",
			"system" => "on"
		));
		$db->query("CREATE TABLE `bigtree_login_attempts` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$db->query("CREATE TABLE `bigtree_login_bans` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP, `expires` datetime DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

		// Media settings
		$db->insert("bigtree_settings", array(
			"id" => "bigtree-internal-media-settings",
			"value" => "{}",
			"system" => "on"
		));

		// New field types
		BigTree::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");

		// Setup an anonymous function for converting a resource set
		$resource_converter = function($resources) {
			$new_resources = array();
			foreach ($resources as $item) {
				// Array of Items no longer exists, switching to Matrix
				if ($item["type"] == "array") {
					$item["type"] = "matrix";
					$item["columns"] = array();
					$x = 0;
					foreach ($item["fields"] as $field) {
						$x++;
						$item["columns"][] = array(
							"id" => $field["key"],
							"type" => $field["type"],
							"title" => $field["title"],
							"display_title" => ($x == 1) ? "on" : ""
						);
					}
					unset($item["fields"]);
				}
				$r = array(
					"id" => $item["id"],
					"type" => $item["type"],
					"title" => $item["title"],
					"subtitle" => $item["subtitle"],
					"options" => array()
				);
				foreach ($item as $key => $val) {
					if ($key != "id" && $key != "title" && $key != "subtitle" && $key != "type") {
						$r["options"][$key] = $val;
					}
				}
				$new_resources[] = $r;
			}
			return BigTree::json($new_resources,true);
		};
		$field_converter = function($fields) {
			$new_fields = array();
			foreach ($fields as $id => $field) {
				// Array of Items no longer exists, switching to Matrix
				if ($field["type"] == "array") {
					$field["type"] = "matrix";
					$field["columns"] = array();
					$x = 0;
					foreach ($field["fields"] as $subfield) {
						$x++;
						$field["columns"][] = array(
							"id" => $subfield["key"],
							"type" => $subfield["type"],
							"title" => $subfield["title"],
							"display_title" => ($x == 1) ? "on" : ""
						);
					}
					unset($field["fields"]);
				}
				$r = array(
					"column" => $id,
					"type" => $field["type"],
					"title" => $field["title"],
					"subtitle" => $field["subtitle"],
					"options" => array()
				);
				foreach ($field as $key => $val) {
					if ($key != "id" && $key != "title" && $key != "subtitle" && $key != "type") {
						$r["options"][$key] = $val;
					}
				}
				$new_fields[] = $r;
			}
			return $new_fields;
		};

		// New resource format to be less restrictive on option names
		$q = $db->query("SELECT * FROM bigtree_callouts");
		while ($f = $q->fetch()) {
			$resources = $resource_converter(json_decode($f["resources"],true));
			$db->query("UPDATE bigtree_callouts SET resources = '$resources' WHERE id = '".$f["id"]."'");
		}
		$q = $db->query("SELECT * FROM bigtree_templates");
		while ($f = $q->fetch()) {
			$resources = $resource_converter(json_decode($f["resources"],true));
			$db->query("UPDATE bigtree_templates SET resources = '$resources' WHERE id = '".$f["id"]."'");
		}
		// Forms and Embedded Forms
		$q = $db->query("SELECT * FROM bigtree_module_forms");
		while ($f = $q->fetch()) {
			$fields = $field_converter(json_decode($f["fields"],true));
			$db->query("UPDATE bigtree_module_forms SET fields = '".BigTree::json($fields,true)."' WHERE id = '".$f["id"]."'");
		}
		$q = $db->query("SELECT * FROM bigtree_module_embeds");
		while ($f = $q->fetch()) {
			$fields = $field_converter(json_decode($f["fields"],true));
			$db->query("UPDATE bigtree_module_embeds SET fields = '".BigTree::json($fields,true)."' WHERE id = '".$f["id"]."'");
		}
		// Settings
		$q = $db->query("SELECT * FROM bigtree_settings WHERE type = 'array'");
		while ($f = $q->fetch()) {
			// Update settings options to turn array into matrix
			$options = json_decode($f["options"],true);
			$options["columns"] = array();
			$x = 0;
			foreach ($options["fields"] as $field) {
				$x++;
				$options["columns"][] = array(
					"id" => $field["key"],
					"type" => $field["type"],
					"title" => $field["title"],
					"display_title" => ($x == 1) ? "on" : ""
				);
				if ($x == 1) {
					$display_key = $field["key"];
				}
			}
			unset($options["fields"]);

			// Update the value to set an internal title key
			$value = BigTreeCMS::getSetting($f["id"]);
			foreach ($value as &$entry) {
				$entry["__internal-title"] = $entry[$display_key];
			}
			unset($entry);

			// Update type/options
			$db->query("UPDATE bigtree_settings SET type = 'matrix', options = '".BigTree::json($options,true)."' WHERE id = '".$f["id"]."'");
			// Update value separately
			BigTreeAdmin::updateSettingValue($f["id"],$value);
		}
	}

	// BigTree 4.2.1 update -- REVISION 201
	function _local_bigtree_update_201() {
		global $db;
		
		setcookie("bigtree_admin[password]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));
		$db->query("CREATE TABLE `bigtree_user_sessions` (`id` varchar(255) NOT NULL DEFAULT '', `email` varchar(255) DEFAULT NULL, `chain` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `email` (`email`), KEY `chain` (`chain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	}

	// BigTree 4.3 update -- REVISION 300
	function _local_bigtree_update_300() {
		global $db;
		
		// Extension settings
		$db->insert("bigtree_settings", array(
			"id" => "bigtree-internal-extension-settings",
			"system" => "on",
			"value" => "{}"
		));

		// New module interface table
		$db->query("CREATE TABLE `bigtree_module_interfaces` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `type` varchar(255) DEFAULT NULL, `module` int(11) DEFAULT NULL, `title` varchar(255) DEFAULT NULL, `table` varchar(255) DEFAULT NULL, `settings` longtext, PRIMARY KEY (`id`), KEY `module` (`module`), KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$intMod = new BigTreeModule("bigtree_module_interfaces");

		// Move forms, views, embeds, and reports into the interfaces table
		$interface_references = array();
		
		// Forms
		$query = $db->query("SELECT * FROM bigtree_module_forms");
		while ($form = $query->fetch()) {
			$interface_references["forms"][$form["id"]] = $intMod->add(array(
				"type" => "form",
				"module" => $form["module"],
				"title" => $form["title"],
				"table" => $form["table"],
				"settings" => array(
					"fields" => $form["fields"],
					"default_position" => $form["default_position"],
					"return_view" => $form["return_view"],
					"return_url" => $form["return_url"],
					"tagging" => $form["tagging"],
					"hooks" => $form["hooks"]
				)
			))
			;
		}

		// Views
		$query = $db->query("SELECT * FROM bigtree_module_views");
		while ($view = $query->fetch()) {
			$interface_references["views"][$view["id"]] = $intMod->add(array(
				"type" => "view",
				"module" => $view["module"],
				"title" => $view["title"],
				"table" => $view["table"],
				"settings" => array(
					"type" => $view["type"],
					"fields" => $view["fields"],
					"options" => $view["options"],
					"actions" => $view["actions"],
					"preview_url" => $view["preview_url"],
					"related_form" => $interface_references["forms"][$view["related_form"]]
				)
			));
		}

		// Go through and updated forms' return views
		$query = $db->query("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'form'");
		while ($form = $query->fetch()) {
			$settings = json_decode($form["settings"],true);
			if ($settings["return_view"]) {
				$settings["return_view"] = $interface_references["views"][$settings["return_view"]];
				$db->update("bigtree_module_interfaces", $form["id"], array("settings" => $settings));
			}
		}

		// Reports
		$query = $db->query("SELECT * FROM bigtree_module_reports");
		while ($report = $query->fetch()) {
			$interface_references["reports"][$report["id"]] = $intMod->add(array(
				"type" => "report",
				"module" => $report["module"],
				"title" => $report["title"],
				"table" => $report["table"],
				"settings" => array(
					"type" => $report["type"],
					"fields" => $report["fields"],
					"filters" => $report["filters"],
					"parser" => $report["parser"],
					"view" => $report["view"]
				)
			));
		}

		// Embeddable Forms
		$query = $db->query("SELECT * FROM bigtree_module_embeds");
		while ($form = $query->fetch()) {
			$intMod->add(array(
				"type" => "embeddable-form",
				"module" => $form["module"],
				"title" => $form["title"],
				"table" => $form["table"],
				"settings" => array(
					"fields" => $form["fields"],
					"default_position" => $form["default_position"],
					"default_pending" => $form["default_pending"],
					"css" => $form["css"],
					"hash" => $form["hash"],
					"redirect_url" => $form["redirect_url"],
					"thank_you_message" => $form["thank_you_message"],
					"hooks" => $form["hooks"]
				)
			));
		}
		
		// Update the module actions to point to the new interface reference
		$db->query("ALTER TABLE `bigtree_module_actions` ADD COLUMN `interface` INT(11) UNSIGNED AFTER `in_nav`");
		$db->query("ALTER TABLE `bigtree_module_actions` ADD FOREIGN KEY (`interface`) REFERENCES `bigtree_module_interfaces` (`id`)");
		$query = $db->query("SELECT * FROM bigtree_module_actions");
		while ($action = $query->fetch()) {
			if ($action["form"]) {
				$db->update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["forms"][$action["form"]]));
			} elseif ($action["view"]) {
				$db->update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["views"][$action["view"]]));
			} elseif ($action["report"]) {
				$db->update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["reports"][$action["report"]]));
			}
		}
		$db->query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `form`");
		$db->query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `view`");
		$db->query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `report`");

		// Drop the old interface tables
		$db->query("SET foreign_key_checks = 0");
		$db->query("DROP TABLE `bigtree_module_embeds`");
		$db->query("DROP TABLE `bigtree_module_forms`");
		$db->query("DROP TABLE `bigtree_module_views`");
		$db->query("DROP TABLE `bigtree_module_reports`");
		$db->query("SET foreign_key_checks = 1");

		// Clear view caches
		$db->query("DELETE FROM bigtree_module_view_cache");

		// Add Developer Only setting to Modules
		$db->query("ALTER TABLE `bigtree_modules` ADD COLUMN `developer_only` CHAR(2) NOT NULL AFTER `gbp`");

		// Change some datetime columns that were only ever the current time of creation / update to be timestamps
		$db->query("ALTER TABLE `bigtree_resource_allocation` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_messages` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_pages` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_resources` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_extensions` CHANGE `last_updated` `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_locks` CHANGE `last_accessed` `last_accessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		$db->query("ALTER TABLE `bigtree_audit_trail` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

		// Fix new window status
		$db->update("bigtree_pages", array("new_window" => "Yes"), array("new_window" => "on"));
		$db->query("UPDATE `bigtree_pages` SET new_window = '' WHERE new_window != 'on'");

		// Remove unused type column
		$db->query("ALTER TABLE `bigtree_pending_changes` DROP COLUMN `type`");

		// Add indexes
		$db->query("CREATE INDEX `name` ON `bigtree_field_types` (`name`)");
		$db->query("CREATE INDEX `name` ON `bigtree_callout_groups` (`name`)");
		$db->query("CREATE INDEX `position` ON `bigtree_callouts` (`position`)");
		$db->query("CREATE INDEX `name` ON `bigtree_callouts` (`name`)");
		$db->query("CREATE INDEX `name` ON `bigtree_extensions` (`name`)");
		$db->query("CREATE INDEX `last_updated` ON `bigtree_extensions` (`last_updated`)");
		$db->query("CREATE INDEX `name` ON `bigtree_feeds` (`name`)");

		// Add user table references
		$db->query("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
		$db->query("ALTER TABLE `bigtree_login_attempts` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
		$db->query("ALTER TABLE `bigtree_login_bans` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");

		// Get rid of unneeded columns
		$db->query("ALTER TABLE `bigtree_locks` DROP COLUMN `title`");
	}
