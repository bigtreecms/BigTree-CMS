<?
	header("Content-type: text/json");

	$admin->verifyCSRFToken();
	$analytics = new BigTreeGoogleAnalyticsAPI;

	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
?>