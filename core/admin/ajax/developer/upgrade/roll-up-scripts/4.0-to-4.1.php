<?php
	// BigTree 4.0 -> 4.1
	
	// Go through all views and figure out what kind of data is in each column.
	$q = sqlquery("SELECT id FROM bigtree_module_views");

	while ($f = sqlfetch($q)) {
		$admin->updateModuleViewColumnNumericStatus(BigTreeAutoModule::getView($f["id"]));
	}

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
	sqlquery("CREATE TABLE `bigtree_module_reports` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL DEFAULT '', `table` varchar(255) NOT NULL, `type` varchar(255) NOT NULL, `filters` text NOT NULL, `fields` text NOT NULL, `parser` varchar(255) NOT NULL DEFAULT '', `view` int(11) unsigned DEFAULT NULL, PRIMARY KEY (`id`), KEY `view` (`view`), KEY `module` (`module`), CONSTRAINT `bigtree_module_reports_ibfk_2` FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE, CONSTRAINT `bigtree_module_reports_ibfk_1` FOREIGN KEY (`view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8");
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
	sqlquery("ALTER TABLE bigtree_module_views ADD COLUMN `module` INT(11) unsigned AFTER `id`");
	sqlquery("ALTER TABLE bigtree_module_views ADD FOREIGN KEY (module) REFERENCES `bigtree_modules` (id) ON DELETE CASCADE");
	// Find all the relevant forms / views / reports and assign them to their proper module.
	$q = sqlquery("SELECT * FROM bigtree_module_actions");
	
	while ($f = sqlfetch($q)) {
		sqlquery("UPDATE bigtree_module_forms SET module = '".$f["module"]."' WHERE id = '".$f["form"]."'");
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

	$admin->updateSettingValue("bigtree-internal-revision", 100);
