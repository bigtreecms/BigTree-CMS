<?php
	// If we're not currently bootstrapped, bootstrap
	if (!isset($cms)) {
		$server_root = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
		include $server_root."custom/environment.php";
		include $server_root."custom/settings.php";
		include $server_root."core/bootstrap.php";		
	}

	$admin = new BigTreeAdmin;

	// Track when we last sent a daily digest
	if (!$admin->settingExists("bigtree-internal-cron-daily-digest-last-sent")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-cron-daily-digest-last-sent",
			"system" => "on"
		));
	}

	$last_sent_daily_digest = $cms->getSetting("bigtree-internal-cron-daily-digest-last-sent");

	// If we last sent the daily digest > ~24 hours ago, send it again. Also refresh analytics.
	if ($last_sent_daily_digest < strtotime("-23 hours 59 minutes")) {
		$admin->updateSettingValue("bigtree-internal-cron-daily-digest-last-sent",time());

		// Send daily digest
		$admin->emailDailyDigest();

		// Cache Google Analytics Information
		$analytics = new BigTreeGoogleAnalyticsAPI;
		if ($analytics->API && $analytics->Profile) {
			$analytics->cacheInformation();
		}
	}

	// Run any extension cron jobs
	$extension_settings = $cms->getSetting("bigtree-internal-extension-settings");
	$cron_settings = $extension_settings["cron"];
	foreach (BigTreeAdmin::$CronPlugins as $extension => $plugins) {
		foreach ($plugins as $id => $details) {
			$id = $extension."*".$id;
			if (empty($cron_settings[$id]["disabled"])) {
				call_user_func($details["function"]);
			}
		}
	}

	// Let the CMS know we're running cron properly
	if (!$admin->settingExists("bigtree-internal-cron-last-run")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-cron-last-run",
			"system" => "on"
		));
	}
	
	// Tell the admin we've ran cron recently.
	$admin->updateSettingValue("bigtree-internal-cron-last-run",time());