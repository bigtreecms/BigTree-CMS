<?php
	$admin->requireLevel(1);
	
	BigTreeCMS::cacheDelete("org.bigtreecms.integritycheck", "session.internal");
	BigTreeCMS::cacheDelete("org.bigtreecms.integritycheck", "session.external");
	
	$admin->growl("Site Integrity Check","Session Reset","success");
	
	BigTree::redirect(MODULE_ROOT."vitals-statistics/integrity/");
	