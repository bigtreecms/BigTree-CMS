<?
	/*
		Class: BigTreeGeocoding
			Geocodes addresses with a variety of services.
	*/
	
	class BigTreeGeocoding {
		
		var $API = false;
		var $Service = "";
		var $Settings = array();
		
		/*
			Constructor:
				Retrieves the current desired service and login credentials.
		*/
		
		function __construct() {
			global $cms;
			$geo_service = $cms->getSetting("bigtree-internal-geocoding-service");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($geo_service) || !$geo_service["service"]) {
				$this->Service = "google";
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-geocoding-service",
					"encrypted" => "on",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-geocoding-service",array("service" => "google"));
			} else {
				$this->Service = $geo_service["service"];
				$this->Settings = $geo_service;
			}
			// Yahoo BOSS Geocoding uses the Yahoo BOSS API.
			if ($this->Service == "yahoo-boss") {
				$this->API = new BigTreeYahooBOSSAPI;
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
			} elseif ($this->Service == "yahoo") {
				$geocode = $this->geocodeYahoo($address);
			} elseif ($this->Service == "yahoo-boss") {
				$geocode = $this->geocodeYahooBOSS($address);
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
				list($latitude,$longitude) = $response["resourceSets"][0]["resources"][0]["point"]["coordinates"];
				if ($latitude && $longitude) {
					return array("latitude" => $latitude, "longitude" => $longitude);
				} else {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
			return false;
		}

		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/

		private function geocodeGoogle($address) {
			$response = BigTree::cURL("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false");
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$latlng = $response["results"][0]["geometry"]["location"];
				if ($latlng["lat"] && $latlng["lng"]) {
					return array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
				} else {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
			return false;
		}

		/*
			Function: geocodeMapQuest
				Private function for using MapQuest as the geocoder.
		*/

		private function geocodeMapQuest($address) {
			$response = BigTree::cURL("http://www.mapquestapi.com/geocoding/v1/address?key=".$this->Settings["mapquest_key"]."&location=".urlencode($address));
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$latlng = $response["results"][0]["locations"][0]["latLng"];
				if ($latlng["lat"] && $latlng["lng"]) {
					return array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
				} else {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
			return false;
		}

		/*
			Function: geocodeYahoo
				Private function for using Yahoo as the geocoder.
		*/

		private function geocodeYahoo($address) {
			$response = BigTree::cURL("http://query.yahooapis.com/v1/public/yql?format=json&q=".urlencode('SELECT * FROM geo.placefinder WHERE text="'.sqlescape($address).'"'));
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$lat = $response["query"]["results"]["Result"]["latitude"];
				$lon = $response["query"]["results"]["Result"]["longitude"];
				if ($lat && $lon) {
					return array("latitude" => $lat, "longitude" => $lon);
				} else {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
			return false;
		}

		/*
			Function: geocodeYahooBOSS
				Private function for using Yahoo BOSS (paid version) as the geocoder.
		*/

		private function geocodeYahooBOSS($address) {
			$response = $this->API->call("geo/placefinder",array("q" => $address,"flags" => "J"));
			if (isset($response->bossresponse->placefinder->results)) {
				$lat = $response->bossresponse->placefinder->results[0]->latitude;
				$lon = $response->bossresponse->placefinder->results[0]->longitude;
				if ($lat && $lon) {
					return array("latitude" => $lat, "longitude" => $lon);
				} else {
					return false;
				}
			}
			return false;
		}
	}
?>