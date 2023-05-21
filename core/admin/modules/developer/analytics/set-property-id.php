<?php
	$analytics = new BigTreeGoogleAnalytics4;
	$analytics->setPropertyID($_POST["property_id"]);
	
	if (!$analytics->testCredentials()) {
		$admin->growl("Analytics", "The Property ID you entered does not appear to be valid.", "error");
		
		BigTree::redirect(DEVELOPER_ROOT."analytics/next-steps/");
		die();
	}
	
	$analytics->setVerified();
	$admin->growl("Analytics", "Property ID Set");
	
	BigTree::redirect(DEVELOPER_ROOT."analytics/");
