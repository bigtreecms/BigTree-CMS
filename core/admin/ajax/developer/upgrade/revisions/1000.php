<?php
	
	namespace BigTree;
	
	// BigTree 5.0 update -- REVISION 1000
		
	// Clear view caches
	SQL::query("DELETE FROM bigtree_module_view_cache");
		
	// Change some datetime columns that were only ever the current time of creation / update to be timestamps
	SQL::query("ALTER TABLE `bigtree_resource_allocation` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_messages` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_pages` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_locks` CHANGE `last_accessed` `last_accessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_audit_trail` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_audit_trail` ADD COLUMN `action` VARCHAR(255) AFTER `type`");
	
	// Change page resources to content
	SQL::query("ALTER TABLE `bigtree_pages` CHANGE `resources` `content` LONGTEXT");
	SQL::query("ALTER TABLE `bigtree_page_revisions` CHANGE `resources` `content` LONGTEXT");
	
	// Fix new window status
	SQL::update("bigtree_pages", ["new_window" => "Yes"], ["new_window" => "on"]);
	SQL::query("UPDATE `bigtree_pages` SET new_window = '' WHERE new_window != 'on'");
	
	// Remove unused type column
	SQL::query("ALTER TABLE `bigtree_pending_changes` DROP COLUMN `type`");
	
	// Add SEO data to be cached to the table instead of dynamic
	SQL::query("ALTER TABLE `bigtree_pages` ADD COLUMN `seo_score` INT(11) NOT NULL DEFAULT 0 AFTER `max_age`");
	SQL::query("ALTER TABLE `bigtree_pages` ADD COLUMN `seo_recommendations` TEXT AFTER `seo_score`");
	
	// Add user table references
	SQL::query("ALTER TABLE `bigtree_user_sessions` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
	SQL::query("ALTER TABLE `bigtree_login_attempts` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
	SQL::query("ALTER TABLE `bigtree_login_bans` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `id`");
	
	// Get rid of unneeded columns
	SQL::query("ALTER TABLE `bigtree_locks` DROP COLUMN `title`");
	
	// Alter Audit Trail to have a consistent add/update/delete type and move actions into action
	SQL::query("UPDATE `bigtree_audit_trail` SET `action` = `type`");
	SQL::query("UPDATE `bigtree_audit_trail` SET `type` = 'add'
				WHERE `type` IN ('created-pending', 'created', 'created via publisher', 'published')");
	SQL::query("UPDATE `bigtree_audit_trail` SET `type` = 'update'
				WHERE `type` IN ('archived', 'archived-inherited', 'ignored', 'moved', 'saved-draft', 'unarchived',
				                 'unarchived-inherited', 'unignored', 'featured', 'unfeatured', 'approved',
				                 'unapproved', 'updated', 'updated via publisher', 'updated-draft')");
	SQL::query("UPDATE `bigtree_audit_trail` SET `type` = 'delete'
				WHERE `type` IN ('deleted', 'deleted-inherited', 'deleted-pending')");
	SQL::query("DELETE FROM `bigtree_audit_trail` WHERE `type` = 'Cleared Empty'");
	SQL::query("UPDATE `bigtree_audit_trail` SET `table` = REPLACE(`table`, 'jsondb -&gt; ', 'config:')");
	
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
	
	// No more messages
	SQL::query("DROP TABLE bigtree_messages");
	
	// Convert views/forms/reports into interfaces, drop embeds
	$modules = DB::getAll("modules");
	
	foreach ($modules as $module) {
		$interfaces = [];
		
		if (is_array($module["forms"])) {
			foreach ($module["forms"] as $form) {
				$interfaces[] = [
					"id" => $form["id"],
					"type" => "form",
					"title" => $form["title"],
					"table" => $form["table"],
					"settings" => [
						"fields" => $form["fields"],
						"default_position" => $form["default_position"],
						"return_view" => $form["return_view"],
						"return_url" => $form["return_url"],
						"open_graph" => $form["open_graph"],
						"tagging" => $form["tagging"],
						"hooks" => $form["hooks"]
					]
				];
			}
		}
		
		if (is_array($module["views"])) {
			foreach ($module["views"] as $view) {
				$interfaces[] = [
					"id" => $view["id"],
					"type" => "view",
					"title" => $view["title"],
					"table" => $view["table"],
					"settings" => [
						"type" => $view["type"],
						"description" => $view["description"],
						"fields" => $view["fields"],
						"settings" => $view["settings"],
						"actions" => $view["actions"],
						"preview_url" => $view["preview_url"],
						"related_form" => $view["related_form"]
					]
				];
			}
		}
		
		if (is_array($module["reports"])) {
			foreach ($module["reports"] as $report) {
				$interfaces[] = [
					"id" => $report["id"],
					"type" => "report",
					"title" => $report["title"],
					"table" => $report["table"],
					"settings" => [
						"type" => $report["type"],
						"filters" => $report["filters"],
						"fields" => $report["fields"],
						"parser" => $report["parser"],
						"view" => $report["view"]
					]
				];
			}
		}
		
		$actions = [];
		
		if (is_array($module["actions"])) {
			foreach ($module["actions"] as $action) {
				if (!empty($action["view"])) {
					$action["interface"] = $action["view"];
				} elseif (!empty($action["form"])) {
					$action["interface"] = $action["form"];
				} elseif (!empty($action["report"])) {
					$action["interface"] = $action["report"];
				} else {
					$action["interface"] = "";
				}

				unset($action["form"]);
				unset($action["report"]);
				unset($action["view"]);

				$actions[] = $action;
			}
		}
		
		DB::update("modules", $module["id"], [
			"interfaces" => $interfaces,
			"actions" => $actions,
			"views" => null,
			"forms" => null,
			"reports" => null,
			"embeddable-forms" => null
		]);
	}
	
	// Convert template/callout resources to fields
	$templates = DB::getAll("templates");
	
	foreach ($templates as $template) {
		DB::update("templates", $template["id"], ["fields" => $template["resources"], "resources" => null]);
	}
	
	$callouts = DB::getAll("callouts");
	
	foreach ($callouts as $callout) {
		DB::update("callouts", $callout["id"], ["fields" => $callout["resources"], "resources" => null]);
	}
	
	echo JSON::encode([
		"complete" => true,
		"response" => "Upgrading to BigTree 5.0"
	]);
	
	Setting::updateValue("bigtree-internal-revision", 1000);
