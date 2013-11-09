<?
	header("Content-type: text/json");

	$analytics = new BigTreeGoogleAnalyticsAPI;
	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
?>