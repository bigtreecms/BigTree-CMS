<?php
	namespace BigTree;
	
	header("Content-type: text/json");
	
	CSRF::verify();

	$analytics = new GoogleAnalytics\API;
	
	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (\Exception $e) {
		echo "false";
	}
	