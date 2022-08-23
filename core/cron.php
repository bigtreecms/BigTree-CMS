<?php
	if (!isset($server_root)) {
		$server_root = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
	}
	
	include $server_root."custom/environment.php";
	include $server_root."custom/settings.php";
	include $server_root."core/bootstrap.php";
	include $server_root."core/inc/bigtree/sitemap.php";

	$admin = new BigTreeAdmin;
	
	// Send out Daily Digests and Content Alerts
	$last_sent = intval($cms->getSetting("bigtree-internal-daily-digest-last-sent"));

	if ((time() - $last_sent) > (24 * 60 * 60)) {
		$admin->emailDailyDigest();
		$admin->updateInternalSettingValue("bigtree-internal-daily-digest-last-sent", time());
	}
	
	// Update tag reference counts
	$admin->updateTagReferenceCounts();
	
	// Cache Google Analytics Information
	$analytics = new BigTreeGoogleAnalyticsAPI;
	if ($analytics->API && $analytics->Profile) {
		$analytics->cacheInformation();
	}
	
	// Tell the admin we've ran cron recently.
	$admin->updateInternalSettingValue("bigtree-internal-cron-last-run", time());

	// Ping bigtreecms.org with current version stats
	if (empty($bigtree["config"]["disable_ping"])) {
		BigTree::cURL("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
	}

	// Re-generate sitemap.xml.
	$sitemap = new BigTreeSitemapGenerator;
	$xml = $sitemap->generateSitemap();
	$sitemap->saveFile($xml);

	// If we're using database-based sessions, do a garbage cleanup (as some server setups will have random gc turned off)
	if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
		BigTreeSessionHandler::clean(ini_get("session.gc_maxlifetime"));
	}
