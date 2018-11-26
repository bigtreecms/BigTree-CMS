<?php
	/*
		Class: BigTreeGeocoding
			Geocodes addresses with a variety of services.
	*/
	
	class BigTreeGeocoding {
		
		public $API = false;
		public $Error = false;
		public $Service = "";
		public $Settings = array();
		
		/*
			Constructor:
				Retrieves the current desired service and login credentials.
		*/
		
		public function __construct() {
			$geo_service = BigTreeCMS::getSetting("bigtree-internal-geocoding-service");

			// If for some reason the setting doesn't exist, make one.
			if (!is_array($geo_service) || empty($geo_service["service"])) {
				$this->Service = "google";
				$admin = new BigTreeAdmin;
				$admin->updateInternalSettingValue("bigtree-internal-geocoding-service", ["service" => "google"], true);
			} else {
				$this->Service = $geo_service["service"];
				$this->Settings = $geo_service;
			}
		}

		/*
			Function: geocode
				Geocodes an address.

			Parameters:
				address - A string of address information to geocode.
				ignore_cache - Whether to re-fetch this geocode, even if we already have it cached. Defaults to false.

			Returns:
				An array containing "latitude" and "longitude" keys if successful, otherwise false.
		*/

		public function geocode($address,$ignore_cache = false) {
			global $cms;
			if (!$address) {
				return false;
			}
			$address = trim($address);

			// Find out if we already have this information in the cache.
			if (!$ignore_cache) {
				$existing = $cms->cacheGet("org.bigtreecms.geocoding",$address);
				if ($existing) {
					return $existing;
				}
			}

			// Geocode
			$geocode = false;
			if ($this->Service == "google" || $this->Service == "yahoo" || $this->Service == "yahoo-boss") {
				$geocode = $this->geocodeGoogle($address);
			} elseif ($this->Service == "bing") {
				$geocode = $this->geocodeBing($address);
			} elseif ($this->Service == "mapquest") {
				$geocode = $this->geocodeMapQuest($address);
			}

			if (!$geocode || !$geocode["latitude"]) {
				return false;
			}
			$cms->cachePut("org.bigtreecms.geocoding",$address,$geocode);
			return $geocode;
		}

		/*
			Function: geocodeBing
				Private function for using Bing as the geocoder.
		*/

		private function geocodeBing($address) {
			$response = BigTree::cURL("http://dev.virtualearth.net/REST/v1/Locations/".str_replace("?","",str_replace(" ","%20",$address))."?key=".$this->Settings["bing_key"]);
			
			try {
				if (is_string($response)) {
					$response = json_decode($response,true);
				}

				if (empty($response["resourceSets"][0]["resources"][0]["point"]["coordinates"])) {
					$this->Error = $response["errorDetails"][0];

					return false;
				}

				list($latitude, $longitude) = $response["resourceSets"][0]["resources"][0]["point"]["coordinates"];
				
				if ($latitude && $longitude) {
					return array("latitude" => $latitude, "longitude" => $longitude);
				} else {
					return false;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();

				return false;
			}
		}

		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/

		private function geocodeGoogle($address) {
			$response = BigTree::cURL("https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false&key=".$this->Settings["google_key"]);

			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}

				if (empty($response["results"][0]["geometry"]["location"])) {
					$this->Error = $response["error_message"];

					return false;
				}

				$latlng = $response["results"][0]["geometry"]["location"];

				if ($latlng["lat"] && $latlng["lng"]) {
					return array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
				} else {
					return false;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();

				return false;
			}
		}

		/*
			Function: geocodeMapQuest
				Private function for using MapQuest as the geocoder.
		*/

		private function geocodeMapQuest($address) {
			global $bigtree;

			$raw_response = BigTree::cURL("http://www.mapquestapi.com/geocoding/v1/address?key=".$this->Settings["mapquest_key"]."&location=".urlencode($address));
			
			if ($bigtree["last_curl_response_code"] != 200) {
				$this->Error = $response;

				return false;
			}

			try {
				if (is_string($raw_response)) {
					$response = json_decode($raw_response, true);
				}

				$latlng = $response["results"][0]["locations"][0]["latLng"];

				if ($latlng["lat"] && $latlng["lng"]) {
					return array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
				} else {
					$this->Error = $raw_response;

					return false;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();

				return false;
			}
		}
	}
