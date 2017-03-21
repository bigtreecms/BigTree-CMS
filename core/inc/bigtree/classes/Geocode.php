<?php
	/*
		Class: BigTree\Geocode
			Provides an interface for geocoding addresses.
	*/
	
	namespace BigTree;
	
	use Exception;
	
	class Geocode {
		
		public static $API = false;
		public static $Service = false;
		public static $Settings = [];
		
		public $Address;
		public $Error;
		public $Latitude;
		public $Longitude;
		
		/*
		    Constructor:
				Creates a Geocode object for the passed in address.

			Parameters:
				address - An address string
				ignore_cache - Whether to ignore the cache and reprocess an address (defaults to false)
		 */
		
		function __construct(string $address, bool $ignore_cache = false) {
			// If we're not sure what service to use yet, check our settings
			if (static::$Service === false) {
				$geo_service = Setting::value("bigtree-internal-geocoding-service");
				
				// If for some reason the setting doesn't exist, make one.
				if (!is_array($geo_service) || !$geo_service["service"]) {
					static::$Service = "google";
					$setting = Setting::create("bigtree-internal-geocoding-service", "Geocoding Service", "", "", [], "", true, true, true);
					$setting->Value = ["service" => "google"];
					$setting->save();
				} else {
					static::$Service = $geo_service["service"];
					static::$Settings = $geo_service;
				}
			}
			
			// No address
			if (!$address) {
				$this->Error = "No address provided.";
				
				return;
			}
			
			$this->Address = trim($address);
			
			// Find out if we already have this information in the cache.
			if (!$ignore_cache) {
				$existing = Cache::get("org.bigtreecms.geocoding", $address);
				
				$this->Latitude = $existing["latitude"];
				$this->Longitude = $existing["longitude"];
				
				return;
			}
			
			// Run the Geocoding APIs
			$result = false;
			
			// Yahoo Placefinder shut down, default to Google
			if (!static::$Service || static::$Service == "google" || static::$Service == "yahoo" || static::$Service == "yahoo-boss") {
				$result = $this->geocodeGoogle($address);
			} elseif (static::$Service == "bing") {
				$result = $this->geocodeBing($address);
			} elseif (static::$Service == "mapquest") {
				$result = $this->geocodeMapQuest($address);
			}
			
			if (empty($result) || !$result["latitude"] || !$result["longitude"]) {
				return;
			}
			
			$this->Latitude = $result["latitude"];
			$this->Longitude = $result["longitude"];
			
			Cache::put("org.bigtreecms.geocoding", $address, $result);
		}
		
		// Magic methods to allow string and array conversion
		function __toString(): string {
			if ($this->Latitude) {
				return $this->Latitude.",".$this->Longitude;
			} else {
				return "";
			}
		}
		
		function __get($property) {
			if ($property == "Array") {
				return ["latitude" => $this->Latitude, "longitude" => $this->Longitude];
			}
			
			trigger_error("Undefined property of BigTree\\Geocode: $property", E_USER_ERROR);
			
			return null;
		}
		
		/*
			Function: geocodeBing
				Private function for using Bing as the geocoder.
		*/
		
		private function geocodeBing(string $address): ?array {
			$address = str_replace("?", "", str_replace(" ", "%20", $address));
			$response = cURL::request("http://dev.virtualearth.net/REST/v1/Locations/$address?key=".static::$Settings["bing_key"]);
			
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				
				if ($response["statusDescription"] != "OK") {
					$this->Error = "Invalid API Key";
					
					return null;
				}
				
				if (count($response["resourceSets"]["resources"]) === 0) {
					$this->Error = "No results for address.";
					
					return null;
				}
				
				list($latitude, $longitude) = $response["resourceSets"][0]["resources"][0]["point"]["coordinates"];
				
				return ["latitude" => $latitude, "longitude" => $longitude];
			} catch (Exception $e) {
				$this->Error = (string) $e;
				
				return null;
			}
		}
		
		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/
		
		private function geocodeGoogle(string $address): ?array {
			$response = cURL::request("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false");
			
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				
				if ($response["status"] == "ZERO_RESULTS") {
					$this->Error = "No results for address.";
					
					return null;
				}
				
				$latlng = $response["results"][0]["geometry"]["location"];
				
				return ["latitude" => $latlng["lat"], "longitude" => $latlng["lng"]];
			} catch (Exception $e) {
				$this->Error = (string) $e;
				
				return null;
			}
		}
		
		/*
			Function: geocodeMapQuest
				Private function for using MapQuest as the geocoder.
		*/
		
		private function geocodeMapQuest(string $address): ?null {
			$response = cURL::request("http://www.mapquestapi.com/geocoding/v1/address?key=".$this->Settings["mapquest_key"]."&location=".urlencode($address));
			
			if ($response == "The AppKey submitted with this request is invalid.") {
				$this->Error = "Invalid API Key";
				
				return null;
			}
			
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				
				$latlng = $response["results"][0]["locations"][0]["latLng"];
				
				if ($latlng["lat"] && $latlng["lng"]) {
					return ["latitude" => $latlng["lat"], "longitude" => $latlng["lng"]];
				} else {
					return null;
				}
			} catch (Exception $e) {
				$this->Error = (string) $e;
				
				return null;
			}
		}
		
	}
	
