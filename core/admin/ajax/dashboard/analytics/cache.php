<?php
	header("Content-type: text/json");

	$analytics = new BigTree\Analytics\Google;
	try {
		$analytics->cacheInformation();
		echo "true";
	} catch (Exception $e) {
		echo "false";
	}