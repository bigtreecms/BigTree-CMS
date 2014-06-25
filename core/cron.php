<?
	$server_root = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
	include $server_root."custom/environment.php";
	include $server_root."custom/settings.php";
	include $server_root."core/bootstrap.php";
	
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		include BigTree::path(BIGTREE_CUSTOM_ADMIN_CLASS_PATH);
		// Can't instantiate class from a constant name, so we use a variable then unset it.
		$c = BIGTREE_CUSTOM_ADMIN_CLASS;
		$admin = new $c;
		unset($c);
	} else {
		include BigTree::path("inc/bigtree/admin.php");
		$admin = new BigTreeAdmin;
	}
	
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
?>