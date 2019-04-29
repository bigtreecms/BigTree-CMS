<?php
	/*
		Class: BigTree\Geocode
			Provides an interface for geocoding addresses.
	*/
	
	namespace BigTree;
	
	use Exception;
	
	class Geocode
	{
		
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
		
		public function __construct(string $address, bool $ignore_cache = false)
		{
			// If we're not sure what service to use yet, check our settings
			if (static::$Service === false) {
				$geo_service = Setting::value("bigtree-internal-geocoding-service");
				
				static::$Service = $geo_service["service"];
				static::$Settings = $geo_service;
			}
			
			// No service
			if (empty(static::$Service)) {
				$this->Error = "Geocoding Service has not beeon configured.";
				
				return;
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
		public function __toString(): string
		{
			if ($this->Latitude) {
				return $this->Latitude.",".$this->Longitude;
			} else {
				return "";
			}
		}
		
		public function __get($property)
		{
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
		
		private function geocodeBing(string $address): ?array
		{
			$address = str_replace("?", "", str_replace(" ", "%20", $address));
			$response = cURL::request("http://dev.virtualearth.net/REST/v1/Locations/$address?key=".static::$Settings["bing_key"]);
			
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				
				if (empty($response["resourceSets"][0]["resources"][0]["point"]["coordinates"])) {
					$this->Error = $response["errorDetails"][0];
					
					return null;
				}
				
				list($latitude, $longitude) = $response["resourceSets"][0]["resources"][0]["point"]["coordinates"];
				
				if ($latitude && $longitude) {
					return ["latitude" => $latitude, "longitude" => $longitude];
				} else {
					return null;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();
				
				return null;
			}
		}
		
		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/
		
		private function geocodeGoogle(string $address): ?array
		{
			$response = cURL::request("https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address).
									  "&sensor=false&key=".static::$Settings["google_key"]);
			
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				
				if (empty($response["results"][0]["geometry"]["location"])) {
					$this->Error = $response["error_message"];
					
					return null;
				}
				
				$latlng = $response["results"][0]["geometry"]["location"];
				
				if ($latlng["lat"] && $latlng["lng"]) {
					return ["latitude" => $latlng["lat"], "longitude" => $latlng["lng"]];
				} else {
					return null;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();
				
				return null;
			}
		}
		
		/*
			Function: geocodeMapQuest
				Private function for using MapQuest as the geocoder.
		*/
		
		private function geocodeMapQuest(string $address): ?array
		{
			global $bigtree;
			
			$raw_response = cURL::request("http://www.mapquestapi.com/geocoding/v1/address?key=".
										  static::$Settings["mapquest_key"]."&location=".urlencode($address));
			
			if ($bigtree["last_curl_response_code"] != 200) {
				$this->Error = $raw_response;
				
				return null;
			}
			
			try {
				if (is_string($raw_response)) {
					$response = json_decode($raw_response, true);
				}
				
				$latlng = $response["results"][0]["locations"][0]["latLng"];
				
				if ($latlng["lat"] && $latlng["lng"]) {
					return ["latitude" => $latlng["lat"], "longitude" => $latlng["lng"]];
				} else {
					$this->Error = $raw_response;
					
					return null;
				}
			} catch (Exception $e) {
				$this->Error = $e->getMessage();
				
				return null;
			}
		}
		
	}
	