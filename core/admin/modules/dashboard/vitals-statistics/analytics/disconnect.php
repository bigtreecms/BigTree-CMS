<?php
	$admin->disconnectGoogleAnalytics();
	$analytics->Settings = [];
	$analytics->saveSettings();
	
	BigTree::redirect(MODULE_ROOT);
