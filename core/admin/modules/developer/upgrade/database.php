<?php
	namespace BigTree;
	
	$revision_setting = new Setting("bigtree-internal-revision");
	
	while ($revision_setting->Value < BIGTREE_REVISION) {
		$revision_setting->Value++;
		
		if (function_exists("_local_bigtree_update_".$revision_setting->Value)) {
			call_user_func("_local_bigtree_update_".$revision_setting->Value);
		}
	}
	
	$revision_setting->save();
?>
<div class="container">
	<section>
		<p><?=Text::translate("BigTree has been updated to :version:.", false, array(":version:" => BIGTREE_VERSION))?></p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>"><?=Text::translate("Continue")?></a>
	</footer>
</div>
<?php
	// BigTree 4.0b5 update -- REVISION 1
	function _local_bigtree_update_1() {
		// Update settings to make the value LONGTEXT
		SQL::query("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGTEXT");
		
		// Drop the css/javascript columns from bigtree_module_forms and add preprocess
		SQL::query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `preprocess` VARCHAR(255) NOT NULL AFTER `title`, DROP COLUMN `javascript`, DROP COLUMN `css`");
		
		// Add the "trunk" column to bigtree_pages
		SQL::query("ALTER TABLE `bigtree_pages` ADD COLUMN `trunk` CHAR(2) NOT NULL AFTER `id`");
		SQL::update("bigtree_pages", 0, array("trunk" => "on"));
		
		// Move Google Analytics information into a single setting
		$ga_email = Setting::value("bigtree-internal-google-analytics-email");
		$ga_password = Setting::value("bigtree-internal-google-analytics-password");
		$ga_profile = Setting::value("bigtree-internal-google-analytics-profile");
		
		$ga_setting = new Setting(array(
			"id" => "bigtree-internal-google-analytics",
			"system" => "on",
			"encrypted" => "on"
		));
		$ga_setting->Value = array(
			"email" => $ga_email,
			"password" => $ga_password,
			"profile" => $ga_profile
		);
		$ga_setting->save();
		
		// Move Rackspace into the main upload service
		$rs_containers = Setting::value("bigtree-internal-rackspace-containers");
		$rs_keys = Setting::value("bigtree-internal-rackspace-containers");
		
		// Move Amazon S3 into the main upload service
		$s3_buckets = Setting::value("bigtree-internal-s3-buckets");
		$s3_keys = Setting::value("bigtree-internal-s3-keys");

		// Update the upload service setting to be encrypted.
		$us_setting = new Setting("bigtree-internal-upload-service");
		$us_setting->System = true;
		$us_setting->Encrypted = true;
		$us_setting->Value["rackspace"] = array(
			"containers" => $rs_containers,
			"keys" => $rs_keys
		);
		$us_setting->Value["s3"] = array(
			"buckets" => $s3_buckets,
			"keys" => $s3_keys
		);
		$us_setting->save();
		
		// Create the revision counter
		$revision_setting = new Setting("bigtree-internal-revision");
		$revision_setting->System = true;
		$revision_setting->save();
		
		// Delete all the old settings.
		SQL::query("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-google-analytics-email' OR id = 'bigtree-internal-google-analytics-password' OR id = 'bigtree-internal-google-analytics-profile' OR id = 'bigtree-internal-rackspace-keys' OR id = 'bigtree-internal-rackspace-containers' OR id = 'bigtree-internal-s3-buckets' OR id = 'bigtree-internal-s3-keys'");
	}
	
	// BigTree 4.0b7 update -- REVISION 5
	function _local_bigtree_update_5() {
		// Fixes AES_ENCRYPT not encoding things properly.
		SQL::query("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGBLOB NOT NULL");
		
		// Adds the ability to make a field type available for Settings.
		SQL::query("ALTER TABLE `bigtree_field_types` ADD COLUMN `settings` CHAR(2) NOT NULL AFTER `callouts`");
		
		// Remove uncached.
		SQL::query("ALTER TABLE `bigtree_module_views` DROP COLUMN `uncached`");
		
		// Adds the ability to set options on a setting.
		SQL::query("ALTER TABLE `bigtree_settings` ADD COLUMN `options` TEXT NOT NULL AFTER `type`");
		
		// Alter the module view cache table so that it can be used for custom view caching
		SQL::query("ALTER TABLE `bigtree_module_view_cache` CHANGE `view` `view` VARCHAR(255) NOT NULL");
	}
	
	// BigTree 4.0b7 update -- REVISION 6
	function _local_bigtree_update_6() {
		// Allows null values for module groups and resource folders.
		SQL::query("ALTER TABLE `bigtree_modules` CHANGE `group` `group` INT(11) UNSIGNED DEFAULT NULL");
		SQL::query("ALTER TABLE `bigtree_resources` CHANGE `folder` `folder` INT(11) UNSIGNED DEFAULT NULL");
	}
	
	// BigTree 4.0RC1 update -- REVISION 7
	function _local_bigtree_update_7() {
		// Allow forms to set their return view manually.
		SQL::query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_view` INT(11) UNSIGNED AFTER `default_position`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 8
	function _local_bigtree_update_8() {
		// Remove image an description columns from modules.
		SQL::query("ALTER TABLE `bigtree_modules` DROP COLUMN `image`");
		SQL::query("ALTER TABLE `bigtree_modules` DROP COLUMN `description`");
		/// Remove locked column from pages.
		SQL::query("ALTER TABLE `bigtree_pages` DROP COLUMN `locked`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 9
	function _local_bigtree_update_9() {
		SQL::query("ALTER TABLE `bigtree_tags_rel` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `module`");
		
		// Figure out the table for all the modules and change the tags to be related to the table instead of the module.
		$q = SQL::query("SELECT * FROM bigtree_modules");
		while ($f = $q->fetch()) {
			if (class_exists($f["class"])) {
				$test = new $f["class"];
				SQL::update("bigtree_tags_rel", array("module" => $f["id"]), array("table" => $test->Table));
			}
		}
		SQL::update("bigtree_tags_rel", array("module" => 0), array("table" => "bigtree_pages"));
		
		// And drop the module column.
		SQL::query("ALTER TABLE `bigtree_tags_rel` DROP COLUMN `module`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 10
	function _local_bigtree_update_10() {
		SQL::query("ALTER TABLE `bigtree_modules` ADD COLUMN `icon` VARCHAR(255) NOT NULL AFTER `class`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 11
	function _local_bigtree_update_11() {
		// Got rid of the dropdown for Modules.
		SQL::query("ALTER TABLE `bigtree_module_groups` DROP COLUMN `in_nav`");
		// New Analytics stuff requires that we redo everything.
		SQL::update("bigtree_settings", "bigtree-internal-google-analytics", array("value" => ""));
	}
	
	// BigTree 4.0RC2 update -- REVISION 12
	function _local_bigtree_update_12() {
		// Add the return_url column to bigtree_module_forms.
		SQL::query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_url` VARCHAR(255) NOT NULL AFTER `return_view`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 13
	function _local_bigtree_update_13() {
		// Delete the "package" column from templates.
		SQL::query("ALTER TABLE `bigtree_templates` DROP COLUMN `package`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 14
	function _local_bigtree_update_14() {
		// Allow NULL as an option for the item_id in bigtree_pending_changes
		SQL::query("ALTER TABLE `bigtree_pending_changes` CHANGE `item_id` `item_id` INT(11) UNSIGNED DEFAULT NULL");
		
		// Fix anything that had a 0 before as the item_id and wasn't pages.
		SQL::query("UPDATE `bigtree_pending_changes` SET item_id = NULL WHERE item_id = 0 AND `table` != 'bigtree_pages'");
	}
	
	// BigTree 4.0RC2 update -- REVISION 15
	function _local_bigtree_update_15() {
		// Adds the setting to disable tagging in pages
		$tag_setting = new Setting("bigtree-internal-disable-page-tagging");
		$tag_setting->Type = "checkbox";
		$tag_setting->Name = "Disable Tags in Pages";
		$tag_setting->save();
		
		// Adds a column to module forms to disable tagging.
		SQL::query("ALTER TABLE `bigtree_module_forms` ADD COLUMN `tagging` CHAR(2) NOT NULL AFTER `return_url`");
		
		// Default to tagging being on since it wasn't an option to turn it off previously.
		SQL::query("UPDATE `bigtree_module_forms` SET `tagging` = 'on'");
	}
	
	// BigTree 4.0RC2 update -- REVISION 16
	function _local_bigtree_update_16() {
		// Adds a sort column to the view cache
		SQL::query("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `sort_field` VARCHAR(255) NOT NULL AFTER `group_field`");
		
		// Force all the views to update their cache.
		SQL::query("TRUNCATE TABLE `bigtree_module_view_cache`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 18
	function _local_bigtree_update_18() {
		// Adds a sort column to the view cache
		SQL::query("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `published_gbp_field` TEXT NOT NULL AFTER `gbp_field`");
		
		// Force all the views to update their cache.
		SQL::query("TRUNCATE TABLE `bigtree_module_view_cache`");
	}
	
	// BigTree 4.0RC3 update -- REVISION 19
	function _local_bigtree_update_19() {
		// Add the new caches table
		SQL::query("CREATE TABLE `bigtree_caches` (`identifier` VARCHAR(255) NOT NULL DEFAULT '', `key` VARCHAR(255) NOT NULL DEFAULT '', `value` LONGTEXT, `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}
	
	// BigTree 4.0 update -- REVISION 20
	function _local_bigtree_update_20() {
		// Replace "menu" types with Array of Items
		$options = '{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"link","title":"URL (include http://)","type":"text"}]}';
		SQL::update("bigtree_settings", array("type" => "menu"), array("type" => "array", "options" => $options));
		
		// Replace "many_to_many" with "many-to-many"
		$mtm_find = SQL::escape('"type":"many_to_many"');
		$mtm_replace = SQL::escape('"type":"many-to-many"');
		SQL::query("UPDATE `bigtree_module_forms` SET `fields` = REPLACE(`fields`,'$mtm_find','$mtm_replace')");
	}
	
	// BigTree 4.0 update -- REVISION 21
	function _local_bigtree_update_21() {
		// Fix widths on module view actions
		$q = SQL::query("SELECT * FROM bigtree_module_views");
		
		while ($f = $q->fetch()) {
			$actions = json_decode($f["actions"], true);
			$extra_width = count($actions) * 22; // From 62px to 40px per action.
			$fields = json_decode($f["fields"], true);
			
			foreach ($fields as &$field) {
				if ($field["width"]) {
					$field["width"] += floor($extra_width / count($fields));
				}
			}
			
			SQL::update("bigtree_module_views", $f["id"], array("fields" => $fields));
		}
	}
	
	// BigTree 4.0.1 update -- REVISION 22
	function _local_bigtree_update_22() {
		// Go through all views and figure out what kind of data is in each column.
		$view_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_module_views");
		
		foreach ($view_ids as $id) {
			$view = new ModuleView($id);
			$view->refreshNumericColumns();
		}
	}
	
	// BigTree 4.1 update -- REVISION 100 (incrementing x00 digit for a .1 release)
	function _local_bigtree_update_100() {
		// Turn off foreign keys for the update
		SQL::query("SET SESSION foreign_key_checks = 0");
		
		// MD5 for not duplicating resources that are already uploaded, allocation table for tracking resource usage
		SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `md5` VARCHAR(255) NOT NULL AFTER `file`");
		SQL::query("CREATE TABLE `bigtree_resource_allocation` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `module` VARCHAR(255) DEFAULT NULL, `entry` VARCHAR(255) DEFAULT NULL, `resource` INT(11) UNSIGNED DEFAULT NULL, `updated_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `resource` (`resource`), KEY `updated_at` (`updated_at`), CONSTRAINT `bigtree_resource_allocation_ibfk_1` FOREIGN KEY (`resource`) REFERENCES `bigtree_resources` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
		
		// SEO Invisible for passing X-Robots headers
		SQL::query("ALTER TABLE `bigtree_pages` ADD COLUMN `seo_invisible` CHAR(2) NOT NULL AFTER `meta_description`");
		
		// Per Page Setting
		SQL::query("INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `options`, `name`, `description`, `locked`, `system`, `encrypted`) VALUES ('bigtree-internal-per-page', X'3135', 'text', '', 'Number of Items Per Page', '<p>This should be a numeric amount and controls the number of items per page in areas such as views, settings, users, etc.</p>', 'on', '', '')");
		
		// Module reports
		SQL::query("CREATE TABLE `bigtree_module_reports` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `module` INT(11) UNSIGNED DEFAULT NULL, `title` VARCHAR(255) NOT NULL DEFAULT '', `table` VARCHAR(255) NOT NULL, `type` VARCHAR(255) NOT NULL, `filters` TEXT NOT NULL, `fields` TEXT NOT NULL, `parser` VARCHAR(255) NOT NULL DEFAULT '', `view` INT(11) UNSIGNED DEFAULT NULL, PRIMARY KEY (`id`), KEY `view` (`view`), KEY `module` (`module`), CONSTRAINT `bigtree_module_reports_ibfk_2` FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE, CONSTRAINT `bigtree_module_reports_ibfk_1` FOREIGN KEY (`view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		SQL::query("ALTER TABLE `bigtree_module_actions` ADD COLUMN `report` INT(11) UNSIGNED NULL AFTER `view`");
		
		// Embeddable Module Forms
		SQL::query("CREATE TABLE `bigtree_module_embeds` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `module` INT(11) UNSIGNED DEFAULT NULL, `title` VARCHAR(255) NOT NULL, `preprocess` VARCHAR(255) NOT NULL, `callback` VARCHAR(255) NOT NULL, `table` VARCHAR(255) NOT NULL, `fields` TEXT NOT NULL, `default_position` VARCHAR(255) NOT NULL, `default_pending` CHAR(2) NOT NULL, `css` VARCHAR(255) NOT NULL, `hash` VARCHAR(255) NOT NULL DEFAULT '', `redirect_url` VARCHAR(255) NOT NULL DEFAULT '', `thank_you_message` TEXT NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		
		// Callout groups
		SQL::query("CREATE TABLE `bigtree_callout_groups` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		SQL::query("ALTER TABLE `bigtree_callouts` ADD COLUMN `group` INT(11) UNSIGNED NULL AFTER `position`");
		
		// Create the extensions table (packages for now, 4.2 prep for extensions)
		SQL::query("CREATE TABLE `bigtree_extensions` (`id` VARCHAR(255) NOT NULL DEFAULT '', `type` VARCHAR(255) DEFAULT NULL, `name` VARCHAR(255) DEFAULT NULL, `version` VARCHAR(255) DEFAULT NULL, `last_updated` DATETIME DEFAULT NULL, `manifest` LONGTEXT DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		
		// Get all templates with callouts enabled and provide them with a new resource instead
		$tq = SQL::query("SELECT * FROM bigtree_templates WHERE callouts_enabled = 'on'");
		
		while ($template = $tq->fetch()) {
			$resources = json_decode($template["resources"], true);
			$found = false;
			
			// See if we have a "callouts" resource already
			foreach ($resources as $r) {
				if ($r["id"] == "callouts") {
					$found = true;
				}
			}
			
			// If we already have callouts, use 4.0-callouts
			if ($found) {
				$resources[] = array("id" => "4.0-callouts", "title" => "Callouts", "type" => "callouts");
			} else {
				$resources[] = array("id" => "callouts", "title" => "Callouts", "type" => "callouts");
			}
			
			SQL::update("bigtree_templates", $template["id"], array("resources" => $resources));
			
			// Find pages that use this template
			$q = SQL::query("SELECT * FROM bigtree_pages WHERE template = ? AND callouts != '[]'", $template["id"]);
			
			while ($f = $q->fetch()) {
				$resources = json_decode($f["resources"], true);
				$callouts = json_decode($f["callouts"], true);
				
				if ($found) {
					$resources["4.0-callouts"] = $callouts;
				} else {
					$resources["callouts"] = $callouts;
				}
				
				SQL::update("bigtree_pages", $f["id"], array("resources" => $resources));
			}
		}
		
		// Switch storage settings
		$storage_settings = Setting::value("bigtree-internal-storage");
		if ($storage_settings["service"] == "s3") {
			$cloud = new \BigTreeCloudStorage;
			$cloud->Settings["amazon"] = array("key" => $storage_settings["s3"]["keys"]["access_key_id"], "secret" => $storage_settings["s3"]["keys"]["secret_access_key"]);
			unset($cloud);
		} elseif ($storage_settings["service"] == "rackspace") {
			$cloud = new \BigTreeCloudStorage;
			$cloud->Settings["rackspace"] = array("api_key" => $storage_settings["rackspace"]["keys"]["api_key"], "username" => $storage_settings["rackspace"]["keys"]["username"]);
			unset($cloud);
		}
		SQL::delete("bigtree_settings", "bigtree-internal-storage");
		
		// Adjust module relationships better so that we can just delete a module and have everything cascade delete
		SQL::query("ALTER TABLE bigtree_module_forms ADD COLUMN `module` INT(11) UNSIGNED AFTER `id`");
		SQL::query("ALTER TABLE bigtree_module_forms ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_module_views ADD COLUMN `module` INT(11) UNSIGNED AFTER `id`");
		SQL::query("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
		
		// Find all the relevant forms / views / reports and assign them to their proper module.
		$q = SQL::query("SELECT * FROM bigtree_module_actions");
		while ($f = $q->fetch()) {
			SQL::update("bigtree_module_forms", $f["form"], array("module" => $f["module"]));
			SQL::update("bigtree_module_views", $f["view"], array("module" => $f["module"]));
		}
		
		// Adjust Module Views to use a related form instead of handling suffix tracking
		SQL::query("ALTER TABLE bigtree_module_views ADD COLUMN `related_form` INT(11) UNSIGNED");
		SQL::query("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (related_form) REFERENCES `bigtree_module_forms` (id) ON DELETE SET NULL");
		
		$q = SQL::query("SELECT * FROM bigtree_module_views WHERE suffix != ''");
		while ($f = $q->fetch()) {
			$suffix = SQL::escape($f["suffix"]);
			// Find the related form
			$form_id = SQL::fetchSingle("SELECT form FROM bigtree_module_actions WHERE module = '".$f["module"]."' AND (route = 'add-$suffix' OR route = 'edit-$suffix')");
			if ($form_id) {
				SQL::update("bigtree_module_views", $f["id"], array("related_form" => $form_id));
			}
		}
		
		// Unused columns
		SQL::query("ALTER TABLE `bigtree_module_forms` DROP COLUMN `positioning`");
		SQL::query("ALTER TABLE `bigtree_templates` DROP COLUMN `image`");
		SQL::query("ALTER TABLE `bigtree_templates` DROP COLUMN `callouts_enabled`");
		SQL::query("ALTER TABLE `bigtree_templates` DROP COLUMN `description`");
		SQL::query("ALTER TABLE `bigtree_pages` DROP COLUMN `callouts`");
		SQL::query("ALTER TABLE `bigtree_page_revisions` DROP COLUMN `callouts`");
		SQL::query("ALTER TABLE `bigtree_module_views` DROP COLUMN `suffix`");
		
		// Reinstate foreign keys
		SQL::query("SET SESSION foreign_key_checks = 1");
	}
	
	// BigTree 4.1.1 update -- REVISION 101
	function _local_bigtree_update_101() {
		SQL::query("ALTER TABLE bigtree_caches CHANGE `key` `key` VARCHAR(10000)");
		$storage = new Storage;
		
		if (is_array($storage->Settings["Files"])) {
			foreach ($storage->Settings["Files"] as $file) {
				SQL::insert("bigtree_caches", array(
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
		SQL::query("ALTER TABLE bigtree_field_types ADD COLUMN `use_cases` TEXT NOT NULL AFTER `name`");
		SQL::query("ALTER TABLE bigtree_field_types ADD COLUMN `self_draw` CHAR(2) NULL AFTER `use_cases`");
		$q = SQL::query("SELECT * FROM bigtree_field_types");
		
		while ($f = $q->fetch()) {
			$use_cases = array(
				"templates" => $f["pages"],
				"modules" => $f["modules"],
				"callouts" => $f["callouts"],
				"settings" => $f["settings"]
			);
			
			SQL::update("bigtree_field_types", $f["id"], array("use_cases" => $use_cases));
		}
		
		SQL::query("ALTER TABLE bigtree_field_types DROP `pages`, DROP `modules`, DROP `callouts`, DROP `settings`");
	}
	
	// BigTree 4.1.1 update -- REVISION 103
	function _local_bigtree_update_103() {
		// Converting resource thumbnail sizes to a properly editable feature and naming it better.
		$current = Setting::value("resource-thumbnail-sizes");
		$thumbs = json_decode($current, true);
		$value = array();
		
		foreach (array_filter((array) $thumbs) as $title => $info) {
			$value[] = array("title" => $title, "prefix" => $info["prefix"], "width" => $info["width"], "height" => $info["height"]);
		}
		
		SQL::insert("bigtree_settings", array(
			"id" => "bigtree-file-manager-thumbnail-sizes",
			"value" => $value,
			"type" => "array",
			"options" => '{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"prefix","title":"File Prefix (i.e. thumb_)","type":"text"},{"key":"width","title":"Width","type":"text"},{"key":"height","title":"Height","type":"text"}]}',
			"name" => "File Manager Thumbnail Sizes",
			"locked" => "on"
		));
		SQL::delete("bigtree_settings", "resource-thumbnail-sizes");
	}
	
	// BigTree 4.2 update -- REVISION 200
	function _local_bigtree_update_200() {
		// Drop unused comments column
		SQL::query("ALTER TABLE bigtree_pending_changes DROP COLUMN `comments`");
		
		// Add extension columns
		SQL::query("ALTER TABLE bigtree_callouts ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_callouts ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_feeds ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_feeds ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_field_types ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_field_types ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_modules ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_modules ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_module_groups ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_module_groups ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_settings ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_settings ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		SQL::query("ALTER TABLE bigtree_templates ADD COLUMN `extension` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_templates ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
		
		// New publish_hook column, consolidate other hooks into one column
		SQL::query("ALTER TABLE bigtree_pending_changes ADD COLUMN `publish_hook` VARCHAR(255)");
		SQL::query("ALTER TABLE bigtree_module_forms ADD COLUMN `hooks` TEXT");
		SQL::query("ALTER TABLE bigtree_module_embeds ADD COLUMN `hooks` TEXT");
		
		$q = SQL::query("SELECT * FROM bigtree_module_forms");
		
		while ($f = $q->fetch()) {
			$hooks = array();
			$hooks["pre"] = $f["preprocess"];
			$hooks["post"] = $f["callback"];
			$hooks["publish"] = "";
			SQL::update("bigtree_module_forms", $f["id"], array("hooks" => $hooks));
		}
		
		$q = SQL::query("SELECT * FROM bigtree_module_embeds");
		
		while ($f = $q->fetch()) {
			$hooks = array();
			$hooks["pre"] = $f["preprocess"];
			$hooks["post"] = $f["callback"];
			$hooks["publish"] = "";
			SQL::update("bigtree_module_embeds", $f["id"], array("hooks" => $hooks));
		}
		
		SQL::query("ALTER TABLE bigtree_module_forms DROP COLUMN `preprocess`");
		SQL::query("ALTER TABLE bigtree_module_forms DROP COLUMN `callback`");
		SQL::query("ALTER TABLE bigtree_module_embeds DROP COLUMN `preprocess`");
		SQL::query("ALTER TABLE bigtree_module_embeds DROP COLUMN `callback`");
		
		// Adjust groups/callouts for multi-support -- first we drop the foreign key
		$table_desc = SQL::describeTable("bigtree_callouts");
		foreach ($table_desc["foreign_keys"] as $name => $definition) {
			if ($definition["local_columns"][0] === "group") {
				SQL::query("ALTER TABLE bigtree_callouts DROP FOREIGN KEY `$name`");
			}
		}
		
		// Add the field to the groups
		SQL::query("ALTER TABLE bigtree_callout_groups ADD COLUMN `callouts` TEXT AFTER `name`");
		
		// Find all the callouts in each group
		$q = SQL::query("SELECT * FROM bigtree_callout_groups");
		
		while ($f = $q->fetch()) {
			$callouts = array();
			$qq = SQL::query("SELECT * FROM bigtree_callouts WHERE `group` = '".$f["id"]."' ORDER BY position DESC, id ASC");
			
			while ($ff = $qq->fetch()) {
				$callouts[] = $ff["id"];
			}
			
			SQL::update("bigtree_callout_groups", $f["id"], array("callouts" => $callouts));
		}
		
		// Drop the group column
		SQL::query("ALTER TABLE bigtree_callouts DROP COLUMN `group`");
		
		// Security policy setting
		SQL::insert("bigtree_settings", array(
			"id" => "bigtree-internal-security-policy",
			"value" => "{}",
			"system" => "on"
		));
		SQL::query("CREATE TABLE `bigtree_login_attempts` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `ip` INT(11) DEFAULT NULL, `user` INT(11) DEFAULT NULL, `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		SQL::query("CREATE TABLE `bigtree_login_bans` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `ip` INT(11) DEFAULT NULL, `user` INT(11) DEFAULT NULL, `created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, `expires` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		
		// Media settings
		SQL::insert("bigtree_settings", array(
			"id" => "bigtree-internal-media-settings",
			"value" => "{}",
			"system" => "on"
		));
		
		// New field types
		FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
		
		// Setup an anonymous function for converting a resource set
		$resource_converter = function ($resources) {
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
			
			return JSON::encode($new_resources, true);
		};
		
		$field_converter = function ($fields) {
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
		$q = SQL::query("SELECT * FROM bigtree_callouts");
		
		while ($f = $q->fetch()) {
			$resources = $resource_converter(json_decode($f["resources"], true));
			SQL::query("UPDATE bigtree_callouts SET resources = '$resources' WHERE id = '".$f["id"]."'");
		}
		
		$q = SQL::query("SELECT * FROM bigtree_templates");
		
		while ($f = $q->fetch()) {
			$resources = $resource_converter(json_decode($f["resources"], true));
			SQL::query("UPDATE bigtree_templates SET resources = '$resources' WHERE id = '".$f["id"]."'");
		}
		
		// Forms and Embedded Forms
		$q = SQL::query("SELECT * FROM bigtree_module_forms");
		
		while ($f = $q->fetch()) {
			$fields = $field_converter(json_decode($f["fields"], true));
			SQL::query("UPDATE bigtree_module_forms SET fields = '".JSON::encode($fields, true)."' WHERE id = '".$f["id"]."'");
		}
		
		$q = SQL::query("SELECT * FROM bigtree_module_embeds");
		
		while ($f = $q->fetch()) {
			$fields = $field_converter(json_decode($f["fields"], true));
			SQL::query("UPDATE bigtree_module_embeds SET fields = '".JSON::encode($fields, true)."' WHERE id = '".$f["id"]."'");
		}
		
		// Settings
		$q = SQL::query("SELECT * FROM bigtree_settings WHERE type = 'array'");
		
		while ($f = $q->fetch()) {
			// Update settings options to turn array into matrix
			$options = json_decode($f["options"], true);
			$options["columns"] = array();
			$x = 0;
			$display_key = false;
			
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
			$value = Setting::value($f["id"]);
			
			foreach ($value as &$entry) {
				$entry["__internal-title"] = $entry[$display_key];
			}
			
			unset($entry);
			
			// Update type/options
			SQL::query("UPDATE bigtree_settings SET type = 'matrix', options = '".JSON::encode($options, true)."' WHERE id = '".$f["id"]."'");
			
			// Update value separately
			$setting = new Setting($f["id"]);
			$setting->Value = $value;
			$setting->save();
		}
	}
	
	// BigTree 4.2.1 update -- REVISION 201
	function _local_bigtree_update_201() {
		setcookie("bigtree_admin[password]", "", time() - 3600, str_replace(DOMAIN, "", WWW_ROOT));
		SQL::query("CREATE TABLE `bigtree_user_sessions` (`id` VARCHAR(255) NOT NULL DEFAULT '', `email` VARCHAR(255) DEFAULT NULL, `chain` VARCHAR(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `email` (`email`), KEY `chain` (`chain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	}
	
	// BigTree 4.2.10 update -- REVISION 202
	function _local_bigtree_update_202() {
		SQL::query("ALTER TABLE `bigtree_pending_changes` CHANGE COLUMN `user` `user` INT(11) UNSIGNED NULL");
	}
	
	// BigTree 4.2.17 update -- REVISION 203
	function _local_bigtree_update_203() {
		sqlquery("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `csrf_token` VARCHAR(255) NULL");
		sqlquery("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `csrf_token_field` VARCHAR(255) NULL");
		sqlquery("DELETE FROM bigtree_user_sessions");
	}
	
	// BigTree 4.2.19 update -- REVISION 204
	function _local_bigtree_update_204() {
		sqlquery("ALTER TABLE `bigtree_404s` ADD COLUMN `site_key` VARCHAR(255) NULL");
	}
	
	// BigTree 4.2.20 update -- REVISION 205
	function _local_bigtree_update_205() {
		// 4.2.17 broke the 404 list to add duplicates a plenty
		$q = sqlquery("SELECT COUNT(*) AS `count`, `id`, `broken_url` FROM bigtree_404s WHERE `redirect_url` != '' GROUP BY `broken_url`");
		
		// Grab the ones with redirect URLs first as we don't want to mistakenly get the wrong one
		while ($f = sqlfetch($q)) {
			sqlquery("DELETE FROM bigtree_404s WHERE `broken_url` = '".sqlescape($f["broken_url"])."' AND `id` != '".$f["id"]."'");
			sqlquery("UPDATE bigtree_404s SET `requests` = '".$f["count"]."' WHERE `id` = '".$f["id"]."'");
		}
		
		// Now get ones without redirect URLs, doesn't matter which ID
		$q = sqlquery("SELECT COUNT(*) AS `count`, `id`, `broken_url` FROM bigtree_404s WHERE `redirect_url` = '' GROUP BY `broken_url`");
		
		while ($f = sqlfetch($q)) {
			sqlquery("DELETE FROM bigtree_404s WHERE `broken_url` = '".sqlescape($f["broken_url"])."' AND `id` != '".$f["id"]."'");
			sqlquery("UPDATE bigtree_404s SET `requests` = '".$f["count"]."' WHERE `id` = '".$f["id"]."'");
		}
	}
	
	// BigTree 4.3 update -- REVISION 300
	function _local_bigtree_update_300() {
		// Extension settings
		SQL::insert("bigtree_settings", array(
			"id" => "bigtree-internal-extension-settings",
			"system" => "on",
			"value" => "{}"
		));
		
		// New module interface table
		SQL::query("CREATE TABLE `bigtree_module_interfaces` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `type` VARCHAR(255) DEFAULT NULL, `module` INT(11) DEFAULT NULL, `title` VARCHAR(255) DEFAULT NULL, `table` VARCHAR(255) DEFAULT NULL, `settings` LONGTEXT, PRIMARY KEY (`id`), KEY `module` (`module`), KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$intMod = new \BigTreeModule("bigtree_module_interfaces");
		
		// Move forms, views, embeds, and reports into the interfaces table
		$interface_references = array();
		
		// Forms
		$query = SQL::query("SELECT * FROM bigtree_module_forms");
		while ($form = $query->fetch()) {
			$interface_references["forms"][$form["id"]] = $intMod->add(array(
				"type" => "form",
				"module" => $form["module"],
				"title" => $form["title"],
				"table" => $form["table"],
				"settings" => array(
					"fields" => json_decode($form["fields"], true),
					"default_position" => $form["default_position"],
					"return_view" => $form["return_view"],
					"return_url" => $form["return_url"],
					"tagging" => $form["tagging"],
					"hooks" => json_decode($form["hooks"], true)
				)
			));
		}
		
		// Views
		$query = SQL::query("SELECT * FROM bigtree_module_views");
		while ($view = $query->fetch()) {
			$interface_references["views"][$view["id"]] = $intMod->add(array(
				"type" => "view",
				"module" => $view["module"],
				"title" => $view["title"],
				"table" => $view["table"],
				"settings" => array(
					"type" => $view["type"],
					"fields" => json_decode($view["fields"], true),
					"options" => json_decode($view["options"], true),
					"actions" => json_decode($view["actions"], true),
					"preview_url" => $view["preview_url"],
					"related_form" => $interface_references["forms"][$view["related_form"]]
				)
			));
		}
		
		// Go through and updated forms' return views
		$query = SQL::query("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'form'");
		
		while ($form = $query->fetch()) {
			$settings = json_decode($form["settings"], true);
			
			if ($settings["return_view"]) {
				$settings["return_view"] = $interface_references["views"][$settings["return_view"]];
				SQL::update("bigtree_module_interfaces", $form["id"], array("settings" => $settings));
			}
		}
		
		// Reports
		$query = SQL::query("SELECT * FROM bigtree_module_reports");
		
		while ($report = $query->fetch()) {
			$interface_references["reports"][$report["id"]] = $intMod->add(array(
				"type" => "report",
				"module" => $report["module"],
				"title" => $report["title"],
				"table" => $report["table"],
				"settings" => array(
					"type" => $report["type"],
					"fields" => json_decode($report["fields"], true),
					"filters" => json_decode($report["filters"], true),
					"parser" => $report["parser"],
					"view" => $report["view"]
				)
			));
		}
		
		// Embeddable Forms
		$query = SQL::query("SELECT * FROM bigtree_module_embeds");
		
		while ($form = $query->fetch()) {
			$intMod->add(array(
				"type" => "embeddable-form",
				"module" => $form["module"],
				"title" => $form["title"],
				"table" => $form["table"],
				"settings" => array(
					"fields" => json_decode($form["fields"], true),
					"default_position" => $form["default_position"],
					"default_pending" => $form["default_pending"],
					"css" => $form["css"],
					"hash" => $form["hash"],
					"redirect_url" => $form["redirect_url"],
					"thank_you_message" => $form["thank_you_message"],
					"hooks" => json_decode($form["hooks"], true)
				)
			));
		}
		
		// Update the module actions to point to the new interface reference
		SQL::query("ALTER TABLE `bigtree_module_actions` ADD COLUMN `interface` INT(11) UNSIGNED AFTER `in_nav`");
		SQL::query("ALTER TABLE `bigtree_module_actions` ADD FOREIGN KEY (`interface`) REFERENCES `bigtree_module_interfaces` (`id`)");
		$query = SQL::query("SELECT * FROM bigtree_module_actions");
		
		while ($action = $query->fetch()) {
			if ($action["form"]) {
				SQL::update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["forms"][$action["form"]]));
			} elseif ($action["view"]) {
				SQL::update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["views"][$action["view"]]));
			} elseif ($action["report"]) {
				SQL::update("bigtree_module_actions", $action["id"], array("interface" => $interface_references["reports"][$action["report"]]));
			}
		}
		
		SQL::query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `form`");
		SQL::query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `view`");
		SQL::query("ALTER TABLE `bigtree_module_actions` DROP COLUMN `report`");
		
		// Drop the old interface tables
		SQL::query("SET foreign_key_checks = 0");
		SQL::query("DROP TABLE `bigtree_module_embeds`");
		SQL::query("DROP TABLE `bigtree_module_forms`");
		SQL::query("DROP TABLE `bigtree_module_views`");
		SQL::query("DROP TABLE `bigtree_module_reports`");
		SQL::query("SET foreign_key_checks = 1");
		
		// Clear view caches
		SQL::query("DELETE FROM bigtree_module_view_cache");
		
		// Add Developer Only setting to Modules
		SQL::query("ALTER TABLE `bigtree_modules` ADD COLUMN `developer_only` CHAR(2) NOT NULL AFTER `gbp`");
		
		// Change some datetime columns that were only ever the current time of creation / update to be timestamps
		SQL::query("ALTER TABLE `bigtree_resource_allocation` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_messages` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_pages` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_resources` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_extensions` CHANGE `last_updated` `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_locks` CHANGE `last_accessed` `last_accessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
		SQL::query("ALTER TABLE `bigtree_audit_trail` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
		
		// Fix new window status
		SQL::update("bigtree_pages", array("new_window" => "Yes"), array("new_window" => "on"));
		SQL::query("UPDATE `bigtree_pages` SET new_window = '' WHERE new_window != 'on'");
		
		// Remove unused type column
		SQL::query("ALTER TABLE `bigtree_pending_changes` DROP COLUMN `type`");
		
		// Add indexes
		SQL::query("CREATE INDEX `name` ON `bigtree_field_types` (`name`)");
		SQL::query("CREATE INDEX `name` ON `bigtree_callout_groups` (`name`)");
		SQL::query("CREATE INDEX `position` ON `bigtree_callouts` (`position`)");
		SQL::query("CREATE INDEX `name` ON `bigtree_callouts` (`name`)");
		SQL::query("CREATE INDEX `name` ON `bigtree_extensions` (`name`)");
		SQL::query("CREATE INDEX `last_updated` ON `bigtree_extensions` (`last_updated`)");
		SQL::query("CREATE INDEX `name` ON `bigtree_feeds` (`name`)");
		
		// Add user table references
		SQL::query("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
		SQL::query("ALTER TABLE `bigtree_login_attempts` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
		SQL::query("ALTER TABLE `bigtree_login_bans` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
		
		// Get rid of unneeded columns
		SQL::query("ALTER TABLE `bigtree_locks` DROP COLUMN `title`");
		
		// Switch provider names for email service to enable us to load provider classes easier
		if (Setting::exists("bigtree-internal-email-service")) {
			$setting = new Setting("bigtree-internal-email-service");
			
			switch ($setting->Value["service"]) {
				case "local":
					$setting->Value["service"] = "Local";
					break;
				case "mandrill";
					$setting->Value["service"] = "Mandrill";
					break;
				case "mailgun":
					$setting->Value["service"] = "Mailgun";
					break;
				case "postmark":
					$setting->Value["service"] = "Postmark";
					break;
				case "sendgrid":
					$setting->Value["service"] = "SendGrid";
					break;
			}
			
			$setting->save();
		}
		
		// Move Google's Cloud Storage settings into a proper namespace
		$cloud_storage = new Setting("bigtree-internal-cloud-storage");
		
		if ($cloud_storage->Value["key"]) {
			$cloud_storage->Value["google"] = array(
				"key" => $cloud_storage->Value["key"],
				"secret" => $cloud_storage->Value["secret"],
				"project" => $cloud_storage->Value["project"],
				"certificate_email" => $cloud_storage->Value["certificate_email"],
				"private_key" => $cloud_storage->Value["private_key"],
			);
			
			$cloud_storage->save();
		}
		
		// Turn message recipients and read by into JSON
		$messages = SQL::fetchAll("SELECT * FROM bigtree_messages");
		
		foreach ($messages as $message) {
			$message["read_by"] = array_filter(explode("|", $message["read_by"]));
			$message["recipients"] = array_filter(explode("|", $message["recipients"]));
			
			foreach ($message["read_by"] as $index => $reader) {
				$message["read_by"][$index] = strval(intval($reader));
			}
			
			foreach ($message["recipients"] as $index => $recipient) {
				$message["recipients"][$index] = strval(intval($recipient));
			}
			
			SQL::update("bigtree_messages", $message["id"], $message);
		}
	}
