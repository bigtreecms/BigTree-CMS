<?	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	$mroot = ADMIN_ROOT."dashboard/vitals-statistics/analytics/";
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$token = isset($settings["token"]) ? $settings["token"] : "";
	$profile = isset($settings["profile"]) ? $settings["profile"] : "";
	$analytics = new BigTreeGoogleAnalytics;

	if ((!$token || !$profile) && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-profile" && end($bigtree["path"]) != "set-token") {
		BigTree::redirect($mroot."configure/");
	}

	if (file_exists(SERVER_ROOT."cache/analytics.cache")) {
		$cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.cache"),true);
	} else {
		$cache = false;
	}
	
	if (!$cache && end($bigtree["path"]) != "cache" && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-profile" && end($bigtree["path"]) != "set-token") {
		BigTree::redirect($mroot."cache/");
	}
?>