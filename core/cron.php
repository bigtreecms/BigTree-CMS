<?
	$root_path = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
	include $root_path."templates/config.php";
	include $root_path."core/bootstrap.php";
	
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		include BigTree::path(BIGTREE_CUSTOM_ADMIN_CLASS_PATH);
		eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
	} else {
		include BigTree::path("inc/bigtree/admin.php");
		$admin = new BigTreeAdmin;
	}
	
	// Send out Daily Digests and Content Alerts
	$admin->emailDailyDigest();
	
	// Cache Google Analytics Information
	$analytics = new BigTreeGoogleAnalytics;
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