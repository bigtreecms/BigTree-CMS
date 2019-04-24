<?php
	
	namespace BigTree;
	
	// BigTree 5.0 update -- REVISION 1000
	
	// Extension settings
	SQL::insert("bigtree_settings", [
		"id" => "bigtree-internal-extension-settings",
		"system" => "on",
		"value" => "{}"
	]);
		
	// Clear view caches
	SQL::query("DELETE FROM bigtree_module_view_cache");
		
	// Change some datetime columns that were only ever the current time of creation / update to be timestamps
	SQL::query("ALTER TABLE `bigtree_resource_allocation` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_messages` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_pages` CHANGE `updated_at` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_resources` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_locks` CHANGE `last_accessed` `last_accessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
	SQL::query("ALTER TABLE `bigtree_audit_trail` CHANGE `date` `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
	
	// Fix new window status
	SQL::update("bigtree_pages", ["new_window" => "Yes"], ["new_window" => "on"]);
	SQL::query("UPDATE `bigtree_pages` SET new_window = '' WHERE new_window != 'on'");
	
	// Remove unused type column
	SQL::query("ALTER TABLE `bigtree_pending_changes` DROP COLUMN `type`");
		
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
	