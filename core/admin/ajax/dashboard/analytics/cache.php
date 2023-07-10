<?php
	header("Content-type: text/json");

	$admin->verifyCSRFToken();
	$analytics = new BigTreeGoogleAnalytics4;

	try {
		$cache = [];
		$cache["referrers"] = $analytics->getMultipleMetricsForDimension(["sessions", "screenPageViews"], "sessionSource");
		$cache["browsers"] = $analytics->getMultipleMetricsForDimension(["sessions", "screenPageViews"], "browser");
		
		
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
