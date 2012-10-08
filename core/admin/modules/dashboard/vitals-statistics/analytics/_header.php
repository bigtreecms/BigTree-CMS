<?	
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	$mroot = ADMIN_ROOT."dashboard/vitals-statistics/analytics/";

	$breadcrumb = array(
		array("link" => "dashboard/", "title" => "Dashboard"),
		array("link" => "dashboard/vitals-statistics/", "title" => "Vitals &amp; Statistics"),
		array("link" => "dashboard/vitals-statistics/analytics/", "title" => "Analytics")
	);
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$token = isset($settings["token"]) ? $settings["token"] : "";
	$profile = isset($settings["profile"]) ? $settings["profile"] : "";
	$analytics = new BigTreeGoogleAnalytics;
?>