<?
	/*
		Class: BigTreeGeocodingService
			Geocodes addresses with a variety of services.
	*/
	
	class BigTreeGeocodingService {
		
		var $Service = "";
		
		/*
			Constructor:
				Retrieves the current desired service and login credentials.
		*/
		
		function __construct() {
			global $cms,$admin;
			$geo_service = $cms->getSetting("bigtree-internal-geocoding-service");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($geo_service) || !$geo_service["service"]) {
				$this->Service = "google";
				$admin->createSetting(array(
					"id" => "bigtree-internal-geocoding-service",
					"encrypted" => "on",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-geocoding-service",array("service" => "google"));
			} else {
				$this->Service = $geo_service["service"];
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

		function geocode($address,$ignore_cache = false) {
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
			if ($this->Service == "google") {
				$geocode = $this->geocodeGoogle($address);
			}

			if (!$geocode) {
				return false;
			}
			$cms->cachePut("org.bigtreecms.geocoding",$address,$geocode);
			return $geocode;
		}

		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/

		private function geocodeGoogle($address) {
			$response = BigTree::curl("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false");
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$latlng = $response["results"][0]["geometry"]["location"];
				$geo = array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
			} catch (Exception $e) {
				$geo = false;
			}
			return $geo;
		}
	}