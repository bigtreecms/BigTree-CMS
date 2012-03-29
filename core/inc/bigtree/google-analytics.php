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
				
			Parameters:
				email - Google account email/username
				password - Google account password
				profile - Google Analytics profile ID *(optional)*
		*/
		
		function __construct($email = false,$password = false,$profile = false) {
			if (!$email) {
				global $cms;
				$email = $cms->getSetting("bigtree-internal-google-analytics-email");
				$password = $cms->getSetting("bigtree-internal-google-analytics-password");
				$profile = $cms->getSetting("bigtree-internal-google-analytics-profile");
			}
			
			$response = $this->httpRequest("https://www.google.com/accounts/ClientLogin",null,array(
				'accountType' => 'GOOGLE',
				'Email' => $email,
				'Passwd' => $password,
				'source' => "BigTree Google Analytics Library",
				'service' => 'analytics'
			));
			
			parse_str(str_replace(array("\n","\r\n"),'&',$response['body']),$auth_token);
			
			$this->AuthToken = $auth_token['Auth'];
			$this->Profile = $profile;
		}
		
		/*
			Function: cacheInformation
				Retrieves analytics information for each BigTree page and saves it in bigtree_pages for fast reference later.
				Also retrieves heads up views and stores them in settings for quick retrieval. Should be called every 24 hours to refresh.
		*/
		
		function cacheInformation() {
			global $admin;
			
			$used_paths = array();
			$cache = array();
			
			// Setup the request parameters for Google.  First we're going to get every page in the site and get its views over the past 30 days.
			$parameters = array();
			$parameters["ids"] = "ga:".$this->Profile;
			$parameters['dimensions'] = "ga:pagePath";
			$parameters['metrics'] = "ga:pageviews";
			$parameters['sort'] = "ga:pagePath";
			$parameters['start-date'] = date('Y-m-d',strtotime('1 month ago'));
			$parameters['end-date'] = date("Y-m-d");
			$parameters['start-index'] = 1;
			$parameters['max-results'] = 100000;
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			
			$xml = simplexml_load_string($response["body"]);
			
			foreach ($xml->entry as $entry) {
				$metrics = array();
				foreach ($entry->children('http://schemas.google.com/analytics/2009')->metric as $metric) {
					$metric_value = strval($metric->attributes()->value);
					//Check for float, or value with scientific notation
					if (preg_match('/^(\d+\.\d+)|(\d+E\d+)|(\d+.\d+E\d+)$/',$metric_value)) {
						$metrics[str_replace('ga:','',$metric->attributes()->name)] = floatval($metric_value);
					} else {
						$metrics[str_replace('ga:','',$metric->attributes()->name)] = intval($metric_value);
					}
				}
				
				$dimensions = array();
				foreach ($entry->children('http://schemas.google.com/analytics/2009')->dimension as $dimension) {
					$dimensions[str_replace('ga:','',$dimension->attributes()->name)] = strval($dimension->attributes()->value);
				}
				
				$clean_path = trim($dimensions["pagePath"],"/");
				
				// Sometimes Google has slightly different routes like "cheese" and "cheese/" so we need to add these page views together.
				if (in_array($clean_path,$used_paths)) {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = (ga_page_views + ".mysql_real_escape_string($metrics["pageviews"]).") WHERE `path` = '".mysql_real_escape_string($clean_path)."'");
				} else {
					sqlquery("UPDATE bigtree_pages SET ga_page_views = ".mysql_real_escape_string($metrics["pageviews"])." WHERE `path` = '".mysql_real_escape_string($clean_path)."'");
					$used_paths[] = $clean_path;
				}
			}
			// Ok, we're done with the page view count.  Now we're going to get referers and stuff and cache that.
			
			// Let's start with service providers
			$parameters['dimensions'] = "ga:networkLocation";
			// Switch to both visits AND pageviews, sort by pageviews
			$parameters['metrics'] = "ga:pageviews,ga:visits";
			$parameters['sort'] = "-ga:pageviews";
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			
			$xml = simplexml_load_string($response["body"]);
			
			foreach ($xml->entry as $entry) {
				$title = str_replace("ga:networkLocation=","",$entry->title);
				foreach ($entry->children("http://schemas.google.com/analytics/2009") as $key => $v) {
					if ($key == "metric") {
						$a = $v->attributes();
						if ($a["name"] == "ga:pageviews") {
							$views = intval($a["value"]);
						} else {
							$visits = intval($a["value"]);
						}
					}
				}
				$cache["service_providers"][] = array("name" => $title, "views" => $views, "visits" => $visits);
			}
			
			// Let's do referrers
			$parameters['dimensions'] = "ga:source";
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			
			$xml = simplexml_load_string($response["body"]);
			
			foreach ($xml->entry as $entry) {
				$title = str_replace("ga:source=","",$entry->title);
				foreach ($entry->children("http://schemas.google.com/analytics/2009") as $key => $v) {
					if ($key == "metric") {
						$a = $v->attributes();
						if ($a["name"] == "ga:pageviews") {
							$views = intval($a["value"]);
						} else {
							$visits = intval($a["value"]);
						}
					}
				}
				$cache["referrers"][] = array("name" => $title, "views" => $views, "visits" => $visits);
			}
			
			// Let's do keywords
			$parameters['dimensions'] = "ga:keyword";
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			
			$xml = simplexml_load_string($response["body"]);
			
			foreach ($xml->entry as $entry) {
				$title = str_replace("ga:keyword=","",$entry->title);
				foreach ($entry->children("http://schemas.google.com/analytics/2009") as $key => $v) {
					if ($key == "metric") {
						$a = $v->attributes();
						if ($a["name"] == "ga:pageviews") {
							$views = intval($a["value"]);
						} else {
							$visits = intval($a["value"]);
						}
					}
				}
				$cache["keywords"][] = array("name" => $title, "views" => $views, "visits" => $visits);
			}
			
			// We swith to browser just because there's usually less to divide on.
			$parameters['dimensions'] = "ga:browser";
			// Get bounces and time on site as well.
			$parameters['metrics'] = "ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite";
			
			// Year
			$parameters['start-date'] = date('Y-01-01');
			$parameters['end-date'] = date("Y-m-d");
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["year"] = $this->parseDataNodes($response["body"]);
			
			// Year ago
			$parameters['start-date'] = date('Y-01-01',strtotime("-1 year"));
			$parameters['end-date'] = date("Y-m-d",strtotime("-1 year"));
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["year_ago_year"] = $this->parseDataNodes($response["body"]);
			
			// Quarter
			$current_quarter_month = date("m") - date("m") % 3 + 1;
			$parameters['start-date'] = date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01");
			$parameters['end-date'] = date("Y-m-d");
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["quarter"] = $this->parseDataNodes($response["body"]);
			
			// Year ago Quarter
			$parameters['start-date'] = date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01",strtotime("-1 year"));
			$parameters['end-date'] = date("Y-m-d",strtotime("-1 year"));
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["year_ago_quarter"] = $this->parseDataNodes($response["body"]);
			
			// Month
			$parameters['start-date'] = date('Y-m-01');
			$parameters['end-date'] = date("Y-m-d");
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["month"] = $this->parseDataNodes($response["body"]);
			
			// Year ago Month
			$parameters['start-date'] = date('Y-m-01',strtotime("-1 year"));
			$parameters['end-date'] = date("Y-m-d",strtotime("-1 year"));
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$cache["year_ago_month"] = $this->parseDataNodes($response["body"]);
			
			// Two Week View
			$parameters['start-date'] = date('Y-m-d',strtotime("-2 weeks"));
			$parameters['end-date'] = date("Y-m-d",strtotime("-1 day"));
			// Switch to the date dimension and just page views metric
			$parameters['dimensions'] = "ga:date";
			$parameters['metrics'] = "ga:visits";
			$parameters['sort'] = "ga:date";
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/data", $parameters, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$xml = simplexml_load_string($response["body"]);
			foreach ($xml->entry as $entry) {
				$title = str_replace("ga:date=","",$entry->title);
				foreach ($entry->children("http://schemas.google.com/analytics/2009") as $key => $v) {
					if ($key == "metric") {
						$a = $v->attributes();
						if ($a["name"] == "ga:visits") {
							$visits = intval($a["value"]);
						}
					}
				}
				$cache["two_week"][$title] = $visits;
			}
			
			// Let's cache this sucker in bigtree_settings now as an internal setting.
			if (!$admin) {
				$admin = new BigTreeAdmin;
			}
			if (!$admin->settingExists("bigtree-internal-google-analytics-cache")) {
				$admin->createSetting(array(
					"id" => "bigtree-internal-google-analytics-cache",
					"name" => "BigTree's Internal Google Analytics Data Cache",
					"system" => "on"
				));
			}
			$admin->updateSettingValue("bigtree-internal-google-analytics-cache",$cache);
		}
		
		/*
			Function: getAvailableProfiles
				Returns a list of accounts and profiles in each of the accounts
			
			Returns:
				An array of accounts.
				The key of each element is the account name, the value is an array of profiles in the given account with "title" and "id" for each profile.
		*/
		
		function getAvailableProfiles() {
			// Get the information from Google
			$response = $this->httpRequest("https://www.google.com/analytics/feeds/accounts/default", null, null, array('Authorization: GoogleLogin auth='.$this->AuthToken));
			$xml = simplexml_load_string($response["body"]);
			
			$accounts = array();
			$account_names = array();
			$results = array();
			$result_names = array();
			
			// Loop through the return information and get its account, profile title, and profile ID
			foreach ($xml->entry as $entry) {
				$properties = array();
				foreach ($entry->children("http://schemas.google.com/analytics/2009")->property as $property) {
					$properties[str_replace("ga:","",$property->attributes()->name)] = strval($property->attributes()->value);
				}
				$results[] = array(
					"id" => str_replace("http://www.google.com/analytics/feeds/accounts/ga:","",$entry->id),
					"title" => strval($entry->title),
					"account" => $properties["accountName"]
				);
				// Store the profile title for sorting alphabetically
				$result_names[] = strval($entry->title);
			}
			
			// Sort the results by the profile title
			array_multisort($result_names,$results);
			
			// Go through each result and assign it back to the account
			foreach ($results as $r) {
				$a = $r["account"];
				unset($r["account"]);
				$accounts[$a][] = $r;
				// Save the account name for sorting
				if (!in_array($a,$account_names)) {
					$account_names[] = $a;
				}
			}
			
			// Sort the accounts alphabetically
			array_multisort($account_names,$accounts);
			
			return $accounts;	
		}
		
		/*
			Function: parseDataNodes
				Private method for cacheInformation to stop so much repeat code.  Returns total visits, views, bounces, and time on site.
		*/
		
		private function parseDataNodes($xml) {
			$xml = simplexml_load_string($xml);
			$views = 0;
			$visits = 0;
			$bounces = 0;
			$time_on_site = 0;
			
			foreach ($xml->entry as $entry) {
				$title = str_replace("ga:browser=","",$entry->title);
				foreach ($entry->children("http://schemas.google.com/analytics/2009") as $key => $v) {
					if ($key == "metric") {
						$a = $v->attributes();
						if ($a["name"] == "ga:pageviews") {
							$views += intval($a["value"]);
						} elseif ($a["name"] == "ga:visits") {
							$visits += intval($a["value"]);
						} elseif ($a["name"] == "ga:bounces") {
							$bounces += intval($a["value"]);
						} else {
							$time_on_site += intval($a["value"]);
						}
					}
				}
				$total_entries++;
			}
			
			// Get average time on the site and make it pretty.
			if (count($xml->entry)) {
				$time_on_site = round($time_on_site / $visits,2);
				$hours = floor($time_on_site / 3600);
				$minutes = floor(($time_on_site - (3600 * $hours)) / 60);
				$seconds = floor($time_on_site - (3600 * $hours) - (60 * $minutes));
				
				$hpart = str_pad($hours,2,"0",STR_PAD_LEFT).":";
				$mpart = str_pad($minutes,2,"0",STR_PAD_LEFT).":";
				$time = $hpart.$mpart.str_pad($seconds,2,"0",STR_PAD_LEFT);
			}
			
			return array("views" => $views,"visits" => $visits,"bounces" => $bounces,"average_time" => $time,"average_time_seconds" => $time_on_site);
		}
		
		
		/*
			Function: httpRequest
				Private method for using cURL (if available) or fopen
		*/
		
		private function httpRequest($url, $get_variables = null, $post_variables = null, $headers = null) {
			if (function_exists('curl_exec')) {
				return $this->curlRequest($url, $get_variables, $post_variables, $headers);
			} else {
				return $this->fopenRequest($url, $get_variables, $post_variables, $headers);
			}
		}
		
		/*
			Function: curlRequest
				Private method for getting the contents of a URL via cURL.
		*/
	
		private function curlRequest($url, $get_variables = null, $post_variables = null, $headers = null) {
			$ch = curl_init();
			if (is_array($get_variables)) {
				$get_variables = '?'.str_replace('&amp;','&',urldecode(http_build_query($get_variables)));
			} else {
				$get_variables = null;
			}
			
			curl_setopt($ch, CURLOPT_URL, $url.$get_variables);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //CURL doesn't like google's cert
			
			if (is_array($post_variables)) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_variables);
			}
			
			if (is_array($headers)) {
				curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
			}
			
			$response = curl_exec($ch);
			$code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			return array('body' => $response,'code' => $code);
		}
		
		/*
			Function: fopenRequest
				Private method for getting the contents of a URL via fopen.
		*/
		
		private function fopenRequest($url, $get_variables = null, $post_variables = null, $headers = null) {
			$http_options = array('method'=>'GET','timeout'=>3);
	
			if (is_array($headers))	{
				$headers = implode("\r\n",$headers)."\r\n";
			} else {
				$headers = '';
			}
			
			if (is_array($get_variables)) {
				$get_variables = '?'.str_replace('&amp;','&',urldecode(http_build_query($get_variables)));
			} else {
				$get_variables = null;
			}
			
			if (is_array($post_variables)) {
				$post_variables = str_replace('&amp;','&',urldecode(http_build_query($post_variables)));
				$http_options['method'] = 'POST';
				$headers = "Content-type: application/x-www-form-urlencoded\r\n"."Content-Length: ".strlen($post_variables)."\r\n".$headers;
				$http_options['header'] = $headers;
				$http_options['content'] = $post_variables;
			} else {
				$post_variables = '';
				$http_options['header'] = $headers;
			}
			
			$context = stream_context_create(array('http' => $http_options));
			$response = @file_get_contents($url.$get_variables, null, $context);	
			
			return array('body' => $response !== false ? $response : 'Request failed, fopen provides no further information','code' => $response !== false ? '200' : '400');
		}
	}
?>