<?
	/*
		Class: BigTreeGoogleAnalytics
			An interface layer for grabbing Google Analytics information and storing it alongside BigTree.
	*/
	
	class BigTreeGoogleAnalytics {
		
		/*
			Constructor:
				Connects to Google to retrieve an Authorization Key for future transactions.
				If no parameters are passed in, this constructor will use Google Analytics defaults from bigtree_settings.
		*/
		
		function __construct() {
			global $cms;

			// Setup Google Analytics API info.
			require_once(BigTree::path("inc/lib/google/apiClient.php"));
			require_once(BigTree::path("inc/lib/google/contrib/apiAnalyticsService.php"));
			$this->Client = new apiClient;
			$this->Client->setClientId('423602902679-h7bva04vid397g496l07csispa6kkth3.apps.googleusercontent.com');
			$this->Client->setClientSecret('lCP25m_7s7o5ua3Z2JY67mRe');
			$this->Client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
			$this->Client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));
			$this->Client->setUseObjects(true);

			$settings = $cms->getSetting("bigtree-internal-google-analytics");
			if (isset($settings["token"]) && $settings["token"]) {
				$this->Client->setAccessToken($settings["token"]);
				$this->API = new apiAnalyticsService($this->Client);
			}
			if (isset($settings["profile"]) && $settings["profile"]) {
				$this->Profile = $settings["profile"];
			}
		}
		
		/*
			Function: cacheInformation
				Retrieves analytics information for each BigTree page and saves it in bigtree_pages for fast reference later.
				Also retrieves heads up views and stores them in settings for quick retrieval. Should be called every 24 hours to refresh.
		*/
		
		function cacheInformation() {
			$cache = array();
			
			// First we're going to update the monthly view counts for all pages.
			$response = $this->API->data_ga->get("ga:".$this->Profile,date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews",array("dimensions" => "ga:pagePath", "sort" => "ga:pagePath", "max-results" => 100000));
			$used_paths = array();
			foreach ($response->rows as $item) {
				$clean_path = sqlescape(trim($item[0],"/"));
				$views = sqlescape($item[1]);
				
				// Sometimes Google has slightly different routes like "cheese" and "cheese/" so we need to add these page views together.
				if (in_array($clean_path,$used_paths)) {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = (ga_page_views + $views) WHERE `path` = '$clean_path'");
				} else {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = $views WHERE `path` = '$clean_path'");
					$used_paths[] = $clean_path;
				}
			}
			
			
			// Service Provider report
			$response = $this->API->data_ga->get("ga:".$this->Profile,date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:networkLocation", "sort" => "-ga:pageviews", "max-results" => 100000));
			
			foreach ($response->rows as $item) {
				$cache["service_providers"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
			}
			
			// Referrer report
			$response = $this->API->data_ga->get("ga:".$this->Profile,date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:source", "sort" => "-ga:pageviews", "max-results" => 100000));
			
			foreach ($response->rows as $item) {
				$cache["referrers"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
			}
			
			// Keyword report
			$response = $this->API->data_ga->get("ga:".$this->Profile,date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:keyword", "sort" => "-ga:pageviews", "max-results" => 100000));
			
			foreach ($response->rows as $item) {
				$cache["keywords"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
			}
			
			// Yearly Report
			$cache["year"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date('Y-01-01'),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
			$cache["year_ago_year"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date('Y-01-01',strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
			
			// Quarterly Report
			$quarters = array(1,3,6,9);
			$current_quarter_month = $quarters[floor((date("m") - 1) / 3)];
			$cache["quarter"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01"),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
			$cache["year_ago_quarter"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01",strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
						
			// Monthly Report
			$cache["month"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date('Y-m-01'),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
			$cache["year_ago_month"] = $this->parseNode($this->API->data_ga->get("ga:".$this->Profile,date('Y-m-01',strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
			
			// Two Week Heads Up
			$response = $this->API->data_ga->get("ga:".$this->Profile,date('Y-m-d',strtotime("-2 weeks")),date("Y-m-d",strtotime("-1 day")),"ga:visits",array("dimensions" => "ga:date", "sort" => "ga:date", "max-results" => 100000));
			foreach ($response->rows as $item) {
				$cache["two_week"][$item[0]] = $item[1];
			}
			
			file_put_contents(SERVER_ROOT."cache/analytics.cache",json_encode($cache));
		}
		
		/*
			Function: parseNode
				Private method for cacheInformation to stop so much repeat code.  Returns total visits, views, bounces, and time on site.
		*/
		
		private function parseNode($node) {
			$views = 0;
			$visits = 0;
			$bounces = 0;
			$time_on_site = 0;
			
			if (count($node->rows)) {
				foreach ($node->rows as $entry) {
					$title = $entry[0];
					$views += $entry[1];
					$visits += $entry[2];
					$bounces += $entry[3];
					$time_on_site += $entry[4];
				}
				
				@$time_on_site = round($time_on_site / $visits,2);
				$hours = floor($time_on_site / 3600);
				$minutes = floor(($time_on_site - (3600 * $hours)) / 60);
				$seconds = floor($time_on_site - (3600 * $hours) - (60 * $minutes));
				
				$hpart = str_pad($hours,2,"0",STR_PAD_LEFT).":";
				$mpart = str_pad($minutes,2,"0",STR_PAD_LEFT).":";
				$time = $hpart.$mpart.str_pad($seconds,2,"0",STR_PAD_LEFT);
			} else {
				$time = "00:00:00";
			}
			
			return array("views" => $views,"visits" => $visits,"bounces" => $bounces,"average_time" => $time,"average_time_seconds" => $time_on_site);
		}
	}
?>