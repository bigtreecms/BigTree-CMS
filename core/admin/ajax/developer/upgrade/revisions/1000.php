<?php
	
	namespace BigTree;
	
	// BigTree 5.0 update -- REVISION 1000
	
	// Extension settings
	SQL::insert("bigtree_settings", [
		"id" => "bigtree-internal-extension-settings",
		"system" => "on",
		"value" => "{}"
	]);
	
	// New module interface table
	SQL::query("CREATE TABLE `bigtree_module_interfaces` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `type` VARCHAR(255) DEFAULT NULL, `module` INT(11) DEFAULT NULL, `title` VARCHAR(255) DEFAULT NULL, `table` VARCHAR(255) DEFAULT NULL, `settings` LONGTEXT, PRIMARY KEY (`id`), KEY `module` (`module`), KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	$intMod = new \BigTreeModule("bigtree_module_interfaces");
	
	// Move forms, views, embeds, and reports into the interfaces table
	$interface_references = [];
	
	// Forms
	$query = SQL::query("SELECT * FROM bigtree_module_forms");
	while ($form = $query->fetch()) {
		$interface_references["forms"][$form["id"]] = $intMod->add([
																	   "type" => "form",
																	   "module" => $form["module"],
																	   "title" => $form["title"],
																	   "table" => $form["table"],
																	   "settings" => [
																		   "fields" => json_decode($form["fields"], true),
																		   "default_position" => $form["default_position"],
																		   "return_view" => $form["return_view"],
																		   "return_url" => $form["return_url"],
																		   "tagging" => $form["tagging"],
																		   "hooks" => json_decode($form["hooks"], true)
																	   ]
																   ]);
	}
	
	// Views
	$query = SQL::query("SELECT * FROM bigtree_module_views");
	while ($view = $query->fetch()) {
		$interface_references["views"][$view["id"]] = $intMod->add([
																	   "type" => "view",
																	   "module" => $view["module"],
																	   "title" => $view["title"],
																	   "table" => $view["table"],
																	   "settings" => [
																		   "type" => $view["type"],
																		   "fields" => json_decode($view["fields"], true),
																		   "options" => json_decode($view["options"], true),
																		   "actions" => json_decode($view["actions"], true),
																		   "preview_url" => $view["preview_url"],
																		   "related_form" => $interface_references["forms"][$view["related_form"]]
																	   ]
																   ]);
	}
	
	// Go through and updated forms' return views
	$query = SQL::query("SELECT * FROM bigtree_module_interfaces WHERE `type` = 'form'");
	
	while ($form = $query->fetch()) {
		$settings = json_decode($form["settings"], true);
		
		if ($settings["return_view"]) {
			$settings["return_view"] = $interface_references["views"][$settings["return_view"]];
			SQL::update("bigtree_module_interfaces", $form["id"], ["settings" => $settings]);
		}
	}
	
	// Reports
	$query = SQL::query("SELECT * FROM bigtree_module_reports");
	
	while ($report = $query->fetch()) {
		$interface_references["reports"][$report["id"]] = $intMod->add([
																		   "type" => "report",
																		   "module" => $report["module"],
																		   "title" => $report["title"],
																		   "table" => $report["table"],
																		   "settings" => [
																			   "type" => $report["type"],
																			   "fields" => json_decode($report["fields"], true),
																			   "filters" => json_decode($report["filters"], true),
																			   "parser" => $report["parser"],
																			   "view" => $report["view"]
																		   ]
																	   ]);
	}
	
	// Embeddable Forms
	$query = SQL::query("SELECT * FROM bigtree_module_embeds");
	
	while ($form = $query->fetch()) {
		$intMod->add([
						 "type" => "embeddable-form",
						 "module" => $form["module"],
						 "title" => $form["title"],
						 "table" => $form["table"],
						 "settings" => [
							 "fields" => json_decode($form["fields"], true),
							 "default_position" => $form["default_position"],
							 "default_pending" => $form["default_pending"],
							 "css" => $form["css"],
							 "hash" => $form["hash"],
							 "redirect_url" => $form["redirect_url"],
							 "thank_you_message" => $form["thank_you_message"],
							 "hooks" => json_decode($form["hooks"], true)
						 ]
					 ]);
	}
	
	// Update the module actions to point to the new interface reference
	SQL::query("ALTER TABLE `bigtree_module_actions` ADD COLUMN `interface` INT(11) UNSIGNED AFTER `in_nav`");
	SQL::query("ALTER TABLE `bigtree_module_actions` ADD FOREIGN KEY (`interface`) REFERENCES `bigtree_module_interfaces` (`id`)");
	$query = SQL::query("SELECT * FROM bigtree_module_actions");
	
	while ($action = $query->fetch()) {
		if ($action["form"]) {
			SQL::update("bigtree_module_actions", $action["id"], ["interface" => $interface_references["forms"][$action["form"]]]);
		} elseif ($action["view"]) {
			SQL::update("bigtree_module_actions", $action["id"], ["interface" => $interface_references["views"][$action["view"]]]);
		} elseif ($action["report"]) {
			SQL::update("bigtree_module_actions", $action["id"], ["interface" => $interface_references["reports"][$action["report"]]]);
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
	SQL::update("bigtree_pages", ["new_window" => "Yes"], ["new_window" => "on"]);
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
		$cloud_storage->Value["google"] = [
			"key" => $cloud_storage->Value["key"],
			"secret" => $cloud_storage->Value["secret"],
			"project" => $cloud_storage->Value["project"],
			"certificate_email" => $cloud_storage->Value["certificate_email"],
			"private_key" => $cloud_storage->Value["private_key"],
		];
		
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
	