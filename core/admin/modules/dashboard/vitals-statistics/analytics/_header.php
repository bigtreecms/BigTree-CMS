<?
	// Setup Google Analytics API info.
	require_once(BigTree::path("inc/lib/google/apiClient.php"));
	require_once(BigTree::path("inc/lib/google/contrib/apiAnalyticsService.php"));
	$client = new apiClient();
	$client->setClientId('423602902679-h7bva04vid397g496l07csispa6kkth3.apps.googleusercontent.com');
	$client->setClientSecret('lCP25m_7s7o5ua3Z2JY67mRe');
	$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
	$client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));
	$client->setUseObjects(true);
	
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
?>