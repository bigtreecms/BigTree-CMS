<?php
	/*
		Class: BigTree\Cron
			Provides an interface for handling BigTree cron tasks.
	*/

	namespace BigTree;

	use BigTree;

	class Cron {

		static $Plugins = array();

		/*
			Function: runs
				Runs all cron jobs.
				Certain tasks will only be run once every 24 hours (Daily Digest, Analytics Caching, Version Ping)
		*/

		static function run() {
			global $bigtree;

			// Track when we last sent a daily digest
			if (!Setting::exists("bigtree-internal-cron-daily-digest-last-sent")) {
				Setting::create("bigtree-internal-cron-daily-digest-last-sent","","","",array(),"",true);
			}
			
			$dailyDigestLastRun = new Setting("bigtree-internal-cron-daily-digest-last-sent");
			$last_sent_daily_digest = $dailyDigestLastRun->Value;
		
			// If we last sent the daily digest > ~24 hours ago, send it again. Also refresh analytics.
			if ($last_sent_daily_digest < strtotime("-23 hours 59 minutes")) {
				$dailyDigestLastRun->Value = time();
				$dailyDigestLastRun->save();
		
				// Send daily digest
				DailyDigest::send();
		
				// Cache Google Analytics Information
				$analytics = new GoogleAnalytics\API;
				if ($analytics->API && $analytics->Profile) {
					$analytics->cacheInformation();
				}

				// Ping bigtreecms.org with current version stats
				if (!$bigtree["config"]["disable_ping"]) {
					BigTree::cURL("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
				}
			}

			// Make sure we have up to date plugins
			Extension::initalizeCache();
		
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
		
			// Let the CMS know we're running cron properly
			if (!Setting::exists("bigtree-internal-cron-last-run")) {
				Setting::create("bigtree-internal-cron-last-run","","","",array(),"",true);
			}

			$cronLastRun = new Setting("bigtree-internal-cron-last-run");
			$cronLastRun->Value = time();
			$cronLastRun->save();
		}
	}
