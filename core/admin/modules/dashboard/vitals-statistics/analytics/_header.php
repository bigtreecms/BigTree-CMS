<?	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	define("MODULE_ROOT",ADMIN_ROOT."dashboard/vitals-statistics/analytics/");
	
	$analytics = new BigTreeGoogleAnalyticsAPI;

	if ((!$analytics->Settings["token"] || !$analytics->Settings["profile"]) && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-profile" && end($bigtree["path"]) != "set-token") {
		BigTree::redirect(MODULE_ROOT."configure/");
	}

	if (file_exists(SERVER_ROOT."cache/analytics.cache")) {
		$cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.cache"),true);
	} else {
		$cache = false;
	}
	
	if (!$cache && end($bigtree["path"]) != "cache" && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-profile" && end($bigtree["path"]) != "set-token") {
		BigTree::redirect(MODULE_ROOT."cache/");
	}
?>