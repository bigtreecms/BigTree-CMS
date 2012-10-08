<?
	header("Content-type: text/json");

	$analytics = new BigTreeGoogleAnalytics;
	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
?>