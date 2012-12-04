<?	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	$mroot = ADMIN_ROOT."dashboard/vitals-statistics/analytics/";
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$token = isset($settings["token"]) ? $settings["token"] : "";
	$profile = isset($settings["profile"]) ? $settings["profile"] : "";
	$analytics = new BigTreeGoogleAnalytics;

	
	if ((!$token || !$profile) && end($bigtree["path"]) != "configure") {
		BigTree::redirect($mroot."configure/");
	}

	$cache = $cms->getSetting("bigtree-internal-google-analytics-cache");
	
	if (!$cache && end($bigtree["path"]) != "configure") {
		BigTree::redirect($mroot."cache/");
	}
?>