<?
	$server_root = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
	include $server_root."custom/environment.php";
	include $server_root."custom/settings.php";
	include $server_root."core/bootstrap.php";
	
	$admin = new BigTreeAdmin;
	
	// Send out Daily Digests and Content Alerts
	$admin->emailDailyDigest();
	
	// Cache Google Analytics Information
	$analytics = new BigTreeGoogleAnalyticsAPI;
	if ($analytics->API && $analytics->Profile) {
		$analytics->cacheInformation();
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

	// Ping bigtreecms.org with current version stats
	if (!$bigtree["config"]["disable_ping"]) {
		BigTree::cURL("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
	}
?>