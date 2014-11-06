<?	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	define("MODULE_ROOT",ADMIN_ROOT."dashboard/vitals-statistics/analytics/");
	
	$analytics = new BigTreeGoogleAnalyticsAPI;
	if (file_exists(SERVER_ROOT."cache/analytics.json")) {
		$cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.json"),true);
	} else {
		$cache = false;
	}
	
	$restricted = array("analytics","keywords","service-providers","traffic-sources");
	if (in_array(end($bigtree["path"]),$restricted)) {
		if (!$analytics->Settings["token"] || !$analytics->Settings["profile"]) {
			BigTree::redirect(MODULE_ROOT."configure/");		
		} elseif (!$cache) {
			BigTree::redirect(MODULE_ROOT."cache/");
		}
	}
?>