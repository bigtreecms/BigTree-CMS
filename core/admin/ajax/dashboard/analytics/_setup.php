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
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$client->setAccessToken($settings["token"]);
	$analytics = new apiAnalyticsService($client);
?>