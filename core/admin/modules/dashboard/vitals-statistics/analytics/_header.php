<?php
	/**
	 * @global BigTreeAdmin $admin
	 */
	
	$admin->requireLevel(1);
	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	define("MODULE_ROOT", ADMIN_ROOT."dashboard/vitals-statistics/analytics/");
	
	$analytics = new BigTreeGoogleAnalytics4;
	
	if (file_exists(SERVER_ROOT."cache/analytics.json")) {
		$cache = json_decode(file_get_contents(SERVER_ROOT."cache/analytics.json"), true);
	} else {
		$cache = false;
	}
	
	if (empty($analytics->Settings["credentials"])) {
		BigTree::redirect(ADMIN_ROOT."developer/analytics/");
	} elseif (!$cache && end($bigtree["path"]) !== "cache") {
		BigTree::redirect(MODULE_ROOT."cache/");
	}
