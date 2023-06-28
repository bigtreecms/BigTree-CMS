<?php
	$analytics = new BigTreeGoogleAnalytics4;
	$analytics->clearCredentials();
	$admin->growl("Analytics", "Disconnected");
	
	BigTree::redirect(DEVELOPER_ROOT."analytics/");
