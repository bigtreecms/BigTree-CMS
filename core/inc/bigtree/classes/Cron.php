<?php
	/*
		Class: BigTree\Cron
			Provides an interface for handling BigTree cron tasks.
	*/
	
	namespace BigTree;
	
	class Cron
	{
		
		public static $Plugins = [];
		
		/*
			Function: runs
				Runs all cron jobs.
				Certain tasks will only be run once every 24 hours (Daily Digest, Analytics Caching, Version Ping)
		*/
		
		public static function run(): void
		{
			global $bigtree;
			
			// Track when we last sent a daily digest
			$last_sent_daily_digest = intval(Setting::value("bigtree-internal-cron-daily-digest-last-sent"));
			
			// If we last sent the daily digest > ~24 hours ago, send it again. Also refresh analytics.
			if ($last_sent_daily_digest < strtotime("-23 hours 59 minutes")) {
				Setting::updateValue("bigtree-internal-cron-daily-digest-last-sent", time());
				
				// Send daily digest
				DailyDigest::send();
				
				// Update tag reference counts
				Tag::updateReferenceCounts();
				
				// Cache Google Analytics Information
				$analytics = new GoogleAnalytics\API;
				
				if ($analytics->Connected && !empty($analytics->Settings["profile"])) {
					$analytics->cacheInformation();
				}
				
				// Ping bigtreecms.org with current version stats
				if (!$bigtree["config"]["disable_ping"]) {
					cURL::request("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
				}
			}

			// Re-cache Sitemap
			Sitemap::cache();
			
			// Make sure we have up to date plugins
			Extension::initializeCache();
			
			// Run any extension cron jobs
			$extension_settings = Setting::value("bigtree-internal-extension-settings");
			$cron_settings = $extension_settings["cron"];
			
			foreach (static::$Plugins as $extension => $plugins) {
				foreach ($plugins as $id => $details) {
					$id = $extension."*".$id;
					
					if (empty($cron_settings[$id]["disabled"])) {
						call_user_func($details["function"]);
					}
				}
			}
			
			Setting::updateValue("bigtree-internal-cron-last-run", time());
		}
		
	}
