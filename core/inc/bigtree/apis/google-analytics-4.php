<?php
	use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
	use Google\Analytics\Data\V1beta\DateRange;
	use Google\Analytics\Data\V1beta\Dimension;
	use Google\Analytics\Data\V1beta\Metric;
	
	/*
		Class: BigTreeGoogleAnalytics4
			An interface for the Google Analytics Data API (GA4).
	*/

	class BigTreeGoogleAnalytics4 {

		private $Client = null;
		public $Settings = [];
		
		public function __construct() {
			$settings = BigTreeCMS::getSetting("bigtree-internal-google-analytics-4");
			
			if ($settings === false) {
				$this->Settings = $settings = ["settings" => []];
				$this->saveSettings();
			}
			
			$this->Settings = $settings;
		}
		
		private function getClient() {
			if (!$this->Client) {
				$this->Client = new BetaAnalyticsDataClient([
					"credentials" => $this->Settings["credentials"],
				]);
			}
			
			return $this->Client;
		}
		
		private function saveSettings() {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-google-analytics-4", $this->Settings, true);
		}
		
		public function cacheInformation() {
			$cache = [];
			
			// First we're going to update the monthly view counts for all pages.
			$results = $this->getSingleMetricForDimension("screenPageViews", "pagePath");
			$used_paths = [];
			
			foreach ($results as $page_path => $view_count) {
				$clean_path = SQL::escape(trim($page_path, "/"));
				$views = intval($view_count);
				
				// Sometimes Google has slightly different routes like "cheese" and "cheese/" so we need to add these page views together.
				if (in_array($clean_path, $used_paths)) {
					SQL::query("UPDATE bigtree_pages SET ga_page_views = (ga_page_views + $views) WHERE `path` = '$clean_path'");
				} else {
					SQL::query("UPDATE bigtree_pages SET ga_page_views = $views WHERE `path` = '$clean_path'");
					$used_paths[] = $clean_path;
				}
			}
			
			// Get quick view reports
			$cache["referrers"] = $this->getMultipleMetricsForDimension(["sessions", "screenPageViews"], "sessionSource");
			$cache["browsers"] = $this->getMultipleMetricsForDimension(["sessions", "screenPageViews"], "browser");
			
			// Yearly Report
			$cache["year"] = $this->cacheParseData(date("Y-01-01"), "today");
			$cache["year_ago_year"] = $this->cacheParseData(date("Y-01-01", strtotime("-1 year")), date("Y-m-d", strtotime("-1 year")));
			
			// Quarterly Report
			$quarters = [1, 3, 6, 9];
			$current_quarter_month = $quarters[floor((date("m") - 1) / 3)];
			
			$cache["quarter"] = $this->cacheParseData(date("Y-".str_pad($current_quarter_month, 2, "0", STR_PAD_LEFT)."-01"), date("Y-m-d"));
			$cache["year_ago_quarter"] = $this->cacheParseData(date("Y-".str_pad($current_quarter_month, 2, "0", STR_PAD_LEFT)."-01", strtotime("-1 year")), date("Y-m-d", strtotime("-1 year")));
			
			// Monthly Report
			$cache["month"] = $this->cacheParseData(date("Y-m-01"), date("Y-m-d"));
			$cache["year_ago_month"] = $this->cacheParseData(date("Y-m-01", strtotime("-1 year")), date("Y-m-d", strtotime("-1 year")));
			
			// Two Week Heads Up
			$cache["two_week"] = $this->getSingleMetricForDimension("sessions", "date", date("Y-m-d", strtotime("-2 weeks")), date("Y-m-d", strtotime("-1 day")));
			ksort($cache["two_week"]);
			
			BigTree::putFile(SERVER_ROOT."cache/analytics.json", BigTree::json($cache));
		}
		
		/*
			Function: cacheParseData
				Private method for cacheInformation to stop so much repeat code.  Returns total visits, views, bounces, and time on site.
		*/
		
		private function cacheParseData($start_date = "30daysAgo", $end_date = "today") {
			$data = $this->getMultipleMetrics(["screenPageViews", "sessions", "bounceRate", "userEngagementDuration"], $start_date, $end_date);
			
			if (!empty($data["sessions"])) {
				$time_on_site = round($data["userEngagementDuration"] / $data["sessions"], 2);
				$hours = floor($time_on_site / 3600);
				$minutes = floor(($time_on_site - (3600 * $hours)) / 60);
				$seconds = floor($time_on_site - (3600 * $hours) - (60 * $minutes));
				
				$hpart = str_pad($hours, 2, "0", STR_PAD_LEFT).":";
				$mpart = str_pad($minutes, 2, "0", STR_PAD_LEFT).":";
				$time = $hpart.$mpart.str_pad($seconds, 2, "0", STR_PAD_LEFT);
			} else {
				$time_on_site = 0;
				$time = "00:00:00";
			}
			
			return [
				"views" => $data["screenPageViews"] ?? 0,
				"visits" => $data["sessions"] ?? 0,
				"bounces" => ceil($data["bounceRate"] ?? 0 * $data["sessions"]),
				"bounce_rate" => ($data["bounceRate"] ?? 0) * 100,
				"average_time" => $time,
				"average_time_seconds" => $time_on_site,
				"total_duration" => $data["userEngagementDuration"] ?? 0,
			];
		}
		
		public function clearCredentials() {
			$this->Settings["verified"] = false;
			$this->Settings["credentials"] = null;
			$this->Settings["property_id"] = "";
			$this->saveSettings();
		}
		
		public function setCredentials($credentials) {
			$this->Settings["verified"] = false;
			$this->Settings["credentials"] = $credentials;
			$this->saveSettings();
		}
		
		public function setPropertyID($property_id) {
			$this->Settings["verified"] = false;
			$this->Settings["property_id"] = $property_id;
			$this->saveSettings();
		}
		
		public function setVerified() {
			$this->Settings["verified"] = true;
			$this->saveSettings();
		}
		
		public function testCredentials() {
			$client = $this->getClient();
			
			try {
				$response = $client->runReport([
					"property" => "properties/".$this->Settings["property_id"],
					"dateRanges" => [new DateRange(["start_date" => "yesterday", "end_date" => "today"])],
					"dimensions" => [new Dimension(["name" => "date"])],
					"metrics" => [new Metric(["name" => "activeUsers"])],
				]);
				
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		
		public function getMetric($metric, $start_date = "30daysAgo", $end_date = "today") {
			$data = $this->getMultipleMetrics([$metric], $start_date, $end_date);
			
			return $data[$metric];
		}
		
		public function getMultipleMetrics($metric_names, $start_date = "30daysAgo", $end_date = "today") {
			$client = $this->getClient();
			$data = [];
			$metrics = [];
			
			foreach ($metric_names as $name) {
				$metrics[] = new Metric(["name" => $name]);
			}
			
			try {
				$response = $client->runReport([
					"property" => "properties/".$this->Settings["property_id"],
					"dateRanges" => [new DateRange(["start_date" => $start_date, "end_date" => $end_date])],
					"metrics" => $metrics,
				]);
				
				foreach ($response->getRows() as $row) {
					$metric_values = $row->getMetricValues();
					
					foreach ($metric_values as $index => $value) {
						$data[$metric_names[$index]] = $value->getValue();
					}
				}
				
				return $data;
			} catch (Exception $e) {
				return [];
			}
		}
		
		public function getSingleMetricForDimension($metric, $dimension, $start_date = "30daysAgo", $end_date = "today") {
			$client = $this->getClient();
			$data = [];
			
			try {
				$response = $client->runReport([
					"property" => "properties/".$this->Settings["property_id"],
					"dateRanges" => [new DateRange(["start_date" => $start_date, "end_date" => $end_date])],
					"dimensions" => [new Dimension(["name" => $dimension])],
					"metrics" => [new Metric(["name" => $metric])],
				]);
				
				foreach ($response->getRows() as $row) {
					$dimension_value = $row->getDimensionValues()->offsetGet(0)->getValue();
					$metric_value = $row->getMetricValues()->offsetGet(0)->getValue();
					$data[$dimension_value] = $metric_value;
				}
				
				return $data;
			} catch (Exception $e) {
				return [];
			}
		}
		
		public function getMultipleMetricsForDimension($metric_names, $dimension, $start_date = "30daysAgo", $end_date = "today") {
			$client = $this->getClient();
			$data = [];
			$metrics = [];
			
			foreach ($metric_names as $name) {
				$metrics[] = new Metric(["name" => $name]);
			}
			
			try {
				$response = $client->runReport([
					"property" => "properties/".$this->Settings["property_id"],
					"dateRanges" => [new DateRange(["start_date" => $start_date, "end_date" => $end_date])],
					"dimensions" => [new Dimension(["name" => $dimension])],
					"metrics" => $metrics,
				]);
				
				foreach ($response->getRows() as $row) {
					$dimension_value = $row->getDimensionValues()->offsetGet(0)->getValue();
					$metric_values = $row->getMetricValues();
					
					foreach ($metric_values as $index => $value) {
						$data[$dimension_value][$metric_names[$index]] = $value->getValue();
					}
				}
				
				return $data;
			} catch (Exception $e) {
				return [];
			}
		}
		
	}
