<?
	$relative_path = "admin/modules/dashboard/vitals-statistics/analytics/";
	$mroot = $admin_root."dashboard/vitals-statistics/analytics/";

	$breadcrumb = array(
		array("link" => "dashboard/", "title" => "Dashboard"),
		array("link" => "dashboard/vitals-statistics/", "title" => "Vitals &amp; Statistics"),
		array("link" => "dashboard/vitals-statistics/analytics/", "title" => "Analytics")
	);
	
	$user = $cms->getSetting("bigtree-internal-google-analytics-email");
	$pass = $cms->getSetting("bigtree-internal-google-analytics-password");
	$profile = $cms->getSetting("bigtree-internal-google-analytics-profile");
?>