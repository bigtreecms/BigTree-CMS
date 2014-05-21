<?
	/*
		Class: BigTreeGoogleAnalytics
			An interface layer for grabbing Google Analytics information and storing it alongside BigTree.
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));
	require_once(BigTree::path("inc/bigtree/apis/_google.result-set.php"));

	class BigTreeGoogleAnalyticsAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		var $ClientID = "423602902679-h7bva04vid397g496l07csispa6kkth3.apps.googleusercontent.com";
		var $ClientSecret = "lCP25m_7s7o5ua3Z2JY67mRe";
		var $EndpointURL = "https://www.googleapis.com/analytics/v3/";
		var $OAuthVersion = "2.0";
		var $RequestType = "header";
		var $Scope = "https://www.googleapis.com/auth/analytics.readonly";
		var $TokenURL = "https://accounts.google.com/o/oauth2/token";

		/*
			Constructor:
				Sets up the Google Analytics API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-google-analytics-api","Google Analytics API","org.bigtreecms.api.analytics.google",$cache);
			$this->Settings["key"] = $this->ClientID;
			$this->Settings["secret"] = $this->ClientSecret;
			$this->ReturnURL = "urn:ietf:wg:oauth:2.0:oob";
		}

		/*
			Function: getAccounts
				Returns a list of all the accounts the authenticated user has access to.

			Parameters:
				params - Additional parameters to pass to the API call.
		
			Returns:
				A BigTreeGoogleResultSet of BigTreeGoogleAnalyticsAccount objects.
		*/

		function getAccounts($params = array()) {
			$response = $this->call("management/accounts",$params);
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $account) {
				$results[] = new BigTreeGoogleAnalyticsAccount($account,$this);
			}
			return new BigTreeGoogleResultSet($this,"getAccounts",array($params),$response,$results);
		}

		/*
			Function: getData
				Returns analytics data.
				For more information on metrics and dimensions, see: https://developers.google.com/analytics/devguides/reporting/core/dimsmets

			Parameters:
				profile - Profile ID
				start_date - Date to begin analytics data report (in a format strtotime understands)
				end_date - Date to end analytics data report (in a format strtotime understands)
				metrics - Array of metrics or a single metric as a string (with or without ga:)
				dimensions - Optional array of dimensions or a single dimension as a string (with or without ga:)
				sort -  Optional metric or dimension to sort by (with or without ga:), optional
				results - Maximum number of results (defaults to 10,000 which is the maximum)

			Returns:
				An array of data.
		*/

		function getData($profile,$start_date,$end_date,$metrics,$dimensions = "",$sort = "",$results = 10000) {
			$start_date = date("Y-m-d",strtotime($start_date));
			$end_date = date("Y-m-d",strtotime($end_date));
			$metric_string = $dimension_string = "";

			// Clean up the metrics
			if (!is_array($metrics)) {
				$metrics = array($metrics);
			}
			foreach ($metrics as $m) {
				if (substr($m,0,3) != "ga:") {
					$metric_string .= "ga:";
				}
				$metric_string .= $m.",";
			}
			$metric_string = rtrim($metric_string,",");

			// Clean up the dimensions
			if (!is_array($dimensions)) {
				$dimensions = array($dimensions);
			}
			foreach ($dimensions as $d) {
				if ($d) {
					if (substr($d,0,3) != "ga:") {
						$dimension_string .= "ga:";
					}
					$dimension_string .= $d.",";
				}
			}
			$dimension_string = rtrim($dimension_string,",");

			// Clean up sort
			if ($sort && substr($sort,0,3) != "ga:" && substr($sort,0,4) != "-ga:") {
				$sort = "ga:$sort";
			}

			$params = array(
				"ids" => "ga:$profile",
				"start-date" => $start_date,
				"end-date" => $end_date,
				"metrics" => rtrim($metric_string,","),
				"max-results" => $results
			);

			if ($dimension_string) {
				$params["dimensions"] = $dimension_string;
			}
			if ($sort) {
				$params["sort"] = $sort;
			}

			$response = $this->call("data/ga",$params);
			if (!$response->rows) {
				return false;
			}

			$this->LastDataTotals = new stdClass;
			foreach ($response->totalsForAllResults as $column => $total) {
				$column = str_replace("ga:","",$column);
				$this->LastDataTotals->$column = $total;
			}

			// Get the names of each column
			$column_names = $results = array();
			foreach ($response->columnHeaders as $header) {
				// Strip ga: from the name
				$column_names[] = substr($header->name,3);
			}
			// Return results
			foreach ($response->rows as $row) {
				$result = new stdClass;
				foreach ($column_names as $index => $name) {
					$result->$name = $row[$index];
				}
				$results[] = $result;
			}
			return $results;
		}

		/*
			Function: getProperties
				Returns web properties for a given account (or all accounts).
		
			Parameters:
				account - Account ID (or "~all" for all accounts which is the default)
				params - Additional parameters to pass to the API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGoogleAnalyticsProperty objects.
		*/

		function getProperties($account = "~all",$params = array()) {
			$response = $this->call("management/accounts/$account/webproperties",$params);
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $property) {
				$results[] = new BigTreeGoogleAnalyticsProperty($property,$this);
			}
			return new BigTreeGoogleResultSet($this,"getProperties",array($account,$params),$response,$results);
		}

		/*
			Function: getProfiles
				Returns views/profiles for a given account (or all accounts) and web property (or all web profiles).
		
			Parameters:
				account - Account ID (or "~all" for all accounts which is the default)
				property - Web Property ID (or "~all" for all profiles which is the default)
				params - Additional parameters to pass to the API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGoogleAnalyticsProfile objects.
		*/

		function getProfiles($account = "~all",$property  = "~all", $params = array()) {
			$response = $this->call("management/accounts/$account/webproperties/$property/profiles",$params);
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $profile) {
				$results[] = new BigTreeGoogleAnalyticsProfile($profile,$this);
			}
			return new BigTreeGoogleResultSet($this,"getProfiles",array($account,$property,$params),$response,$results);
		}
		
		/*
			Function: cacheInformation
				Retrieves analytics information for each BigTree page and saves it in bigtree_pages for fast reference later.
				Also retrieves heads up views and stores them in settings for quick retrieval. Should be called every 24 hours to refresh.
		*/
		
		function cacheInformation() {
			$cache = array();
			
			// First we're going to update the monthly view counts for all pages.
			$results = $this->getData($this->Settings["profile"],"1 month ago","today","pageviews","pagePath");
			$used_paths = array();
			foreach ($results as $item) {
				$clean_path = sqlescape(trim($item->pagePath,"/"));
				$views = sqlescape($item->pageviews);
				
				// Sometimes Google has slightly different routes like "cheese" and "cheese/" so we need to add these page views together.
				if (in_array($clean_path,$used_paths)) {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = (ga_page_views + $views) WHERE `path` = '$clean_path'");
				} else {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = $views WHERE `path` = '$clean_path'");
					$used_paths[] = $clean_path;
				}
			}
			
			// Service Provider report
			$results = $this->getData($this->Settings["profile"],"1 month ago","today",array("pageviews","visits"),"networkLocation","-ga:pageviews");
			foreach ($results as $item) {
				$cache["service_providers"][] = array("name" => $item->networkLocation, "views" => $item->pageviews, "visits" => $item->visits);
			}
			
			// Referrer report
			$results = $this->getData($this->Settings["profile"],"1 month ago","today",array("pageviews","visits"),"source","-ga:pageviews");
			foreach ($results as $item) {
				$cache["referrers"][] = array("name" => $item->source, "views" => $item->pageviews, "visits" => $item->visits);
			}
			
			// Keyword report
			$results = $this->getData($this->Settings["profile"],"1 month ago","today",array("pageviews","visits"),"keyword","-ga:pageviews");
			foreach ($results as $item) {
				$cache["keywords"][] = array("name" => $item->keyword, "views" => $item->pageviews, "visits" => $item->visits);
			}
			
			// Yearly Report
			$this->getData($this->Settings["profile"],date("Y-01-01"),date("Y-m-d"),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["year"] = $this->cacheParseLastData();
			$this->getData($this->Settings["profile"],date("Y-01-01",strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["year_ago_year"] = $this->cacheParseLastData();
			
			// Quarterly Report
			$quarters = array(1,3,6,9);
			$current_quarter_month = $quarters[floor((date("m") - 1) / 3)];

			$this->getData($this->Settings["profile"],date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01"),date("Y-m-d"),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["quarter"] = $this->cacheParseLastData();
			$this->getData($this->Settings["profile"],date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01",strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["year_ago_quarter"] = $this->cacheParseLastData();
						
			// Monthly Report
			$this->getData($this->Settings["profile"],date("Y-m-01"),date("Y-m-d"),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["month"] = $this->cacheParseLastData();
			$this->getData($this->Settings["profile"],date("Y-m-01",strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),array("pageviews","visits","bounces","timeOnSite"),"browser");
			$cache["year_ago_month"] = $this->cacheParseLastData();
			
			// Two Week Heads Up
			$results = $this->getData($this->Settings["profile"],date("Y-m-d",strtotime("-2 weeks")),date("Y-m-d",strtotime("-1 day")),"visits","date","date");
			foreach ($results as $item) {
				$cache["two_week"][$item->date] = $item->visits;
			}
			
			file_put_contents(SERVER_ROOT."cache/analytics.cache",json_encode($cache));
		}
		
		/*
			Function: cacheParseLastData
				Private method for cacheInformation to stop so much repeat code.  Returns total visits, views, bounces, and time on site.
		*/
		
		private function cacheParseLastData() {
			$d = $this->LastDataTotals;

			if ($d->visits) {
				$time_on_site = round($d->timeOnSite / $d->visits,2);
				$hours = floor($time_on_site / 3600);
				$minutes = floor(($time_on_site - (3600 * $hours)) / 60);
				$seconds = floor($time_on_site - (3600 * $hours) - (60 * $minutes));
				
				$hpart = str_pad($hours,2,"0",STR_PAD_LEFT).":";
				$mpart = str_pad($minutes,2,"0",STR_PAD_LEFT).":";
				$time = $hpart.$mpart.str_pad($seconds,2,"0",STR_PAD_LEFT);

				$bounce_rate = round($d->bounces / $d->visits * 100,2);
			} else {
				$time = "00:00:00";
				$bounce_rate = 0;
			}
			
			return array("views" => $d->pageviews,"visits" => $d->visits,"bounces" => $d->bounces,"bounce_rate" => $bounce_rate,"average_time" => $time,"average_time_seconds" => $time_on_site);
		}
	}

	/*
		Class: BigTreeGoogleAnalyticsAccount
			A Google Analytics object that contains information about and methods you can perform on an account.
	*/

	class BigTreeGoogleAnalyticsAccount {
		protected $API;

		function __construct($account,&$api) {
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($account->created));
			$this->ID = $account->id;
			$this->Name = $account->name;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($account->updated));
		}

		function getProperties($params) {
			return $this->API->getProperties($this->ID,$params);
		}
	}

	/*
		Class: BigTreeGoogleAnalyticsProperty
			A Google Analytics object that contains information about and methods you can perform on a property.
	*/

	class BigTreeGoogleAnalyticsProperty {
		protected $API;

		function __construct($property,&$api) {
			$this->AccountID = $property->accountId;
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($property->created));
			$this->ID = $property->id;
			$this->Name = $property->name;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($property->updated));
			$this->WebsiteURL = $property->websiteUrl;
		}

		function getProfiles($params) {
			return $this->API->getProfiles($this->AccountID,$this->ID,$params);
		}
	}

	/*
		Class: BigTreeGoogleAnalyticsProfile
			A Google Analytics object that contains information about and methods you can perform on a profile.
	*/

	class BigTreeGoogleAnalyticsProfile {
		protected $API;

		function __construct($profile,&$api) {
			$this->AccountID = $profile->accountId;
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($profile->created));
			$this->Currency = $profile->currency;
			$this->ID = $profile->id;
			$this->Name = $profile->name;
			$this->PropertyID = $profile->webPropertyId;
			$this->Timezone = $profile->timezone;
			$this->Type = $profile->type;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($profile->updated));
			$this->WebsiteURL = $profile->websiteUrl;
		}
	}
?>