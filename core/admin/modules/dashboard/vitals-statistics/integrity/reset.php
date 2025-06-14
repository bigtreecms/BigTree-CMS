<?php
	$admin->requireLevel(1);

	// Wipe out any existing session data
	BigTreeCMS::cacheDelete("org.bigtreecms.integritycheck");

	$admin->growl("Site Integrity Check","Session Reset","success");
	
	BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/integrity/");
	