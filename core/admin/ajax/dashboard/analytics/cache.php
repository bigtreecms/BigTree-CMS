<?php
	header("Content-type: text/json");

	$analytics = new BigTree\GoogleAnalytics\API;
	
	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}
	