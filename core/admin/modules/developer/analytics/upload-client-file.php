<?php
	// Verify a file was uploaded
	if (empty($_FILES["client_file"]["tmp_name"])) {
		$admin->growl("Analytics", "Please upload a client configuration file.", "error");
		
		BigTree::redirect(DEVELOPER_ROOT."analytics/");
		die();
	}

	// Verify it's actually a client configuration file
	$json = json_decode(file_get_contents($_FILES["client_file"]["tmp_name"]), true);
	
	if (empty($json["private_key"]) || empty($json["client_email"]) || empty($json["client_id"])) {
		$admin->growl("Analytics", "The file you uploaded does not appear to be a valid client configuration file.", "error");
		
		BigTree::redirect(DEVELOPER_ROOT."analytics/");
		die();
	}
	
	$analytics = new BigTreeGoogleAnalytics4;
	$analytics->setCredentials($json);
	
	BigTree::redirect(DEVELOPER_ROOT."analytics/next-steps/");
