<?php
	// Store the BigTree root path.
	$server_root = realpath('') . '/';
	include $server_root."custom/environment.php";
	include $server_root."custom/settings.php";
	include $server_root."core/bootstrap.php";
	include $server_root."core/inc/bigtree/sitemap.php";

	$admin = new BigTreeAdmin;
	
	// Send out Daily Digests and Content Alerts
	$admin->emailDailyDigest();
	
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
	if (!$bigtree["config"]["disable_ping"]) {
		BigTree::cURL("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
	}

	// Re-generate sitemap.xml.
	$sitemap = new BigTreeSitemapGenerator;
	$xml = $sitemap->generateSitemap();
	$sitemap->saveFile($xml);
