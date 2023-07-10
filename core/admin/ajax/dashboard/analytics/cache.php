<?php
	header("Content-type: text/json");

	$admin->verifyCSRFToken();
	$analytics = new BigTreeGoogleAnalytics4;

	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
