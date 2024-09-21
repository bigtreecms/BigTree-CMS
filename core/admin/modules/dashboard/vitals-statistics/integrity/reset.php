<?php
	$admin->requireLevel(1);
	
	BigTreeCMS::cacheDelete("org.bigtreecms.integritycheck", "session.internal");
	BigTreeCMS::cacheDelete("org.bigtreecms.integritycheck", "session.external");
	
	$admin->growl("Site Integrity Check","Session Reset","success");
	
	BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/integrity/");
	