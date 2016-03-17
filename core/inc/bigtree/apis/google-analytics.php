<?php
	/*
		Class: BigTreeGoogleAnalytics
			An interface layer for grabbing Google Analytics information and storing it alongside BigTree.
			DEPRECATED: This remains as a compatibility layer over the BigTree\GoogleAnalytics\API class.
	*/
	
	class BigTreeGoogleAnalytics {

		/*
			Constructor:
				Sets up the Google Analytics API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to false)
		*/

		function __construct($cache = false) {
			$this->API = new BigTree\GoogleAnalytics\API($cache);
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
			return $this->API->getAccount($params);
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
			return $this->API->getData($profile,$start_date,$end_date,$metrics,$dimensions,$sort,$results);
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
			return $this->API->getProperties($account,$params);
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
			return $this->API->getProfiles($account,$property,$params);
		}
		
		/*
			Function: cacheInformation
				Retrieves analytics information for each BigTree page and saves it in bigtree_pages for fast reference later.
				Also retrieves heads up views and stores them in settings for quick retrieval. Should be called every 24 hours to refresh.
		*/
		
		function cacheInformation() {
			return $this->API->cacheInformation();
		}
	}
