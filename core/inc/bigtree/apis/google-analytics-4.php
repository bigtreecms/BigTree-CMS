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
		
	}
