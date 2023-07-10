<?php
	/*
		Class: BigTreeGoogleAnalytics
			This interface no longer works due to Google sunsetting the API.
	*/
	
	class BigTreeGoogleAnalyticsAPI {
		
		public function __construct() {
			trigger_error('BigTreeGoogleAnalyticsAPI is deprecated and no longer functional.', E_USER_DEPRECATED);
		}
		
		public function getAccounts() {
			return null;
		}
		
		public function getData() {
			return null;
		}
		
		public function getProperties() {
			return null;
		}
		
		public function getProfiles() {
			return null;
		}
		
		public function cacheInformation() {
			return null;
		}
	}
	
	class BigTreeGoogleAnalyticsAccount {
		
		public function __construct() {
			trigger_error('BigTreeGoogleAnalyticsAccount is deprecated and no longer functional.', E_USER_DEPRECATED);
		}
		
		public function getProperties() {
			return null;
		}
		
	}
	
	class BigTreeGoogleAnalyticsProperty {
		
		public function __construct() {
			trigger_error('BigTreeGoogleAnalyticsProperty is deprecated and no longer functional.', E_USER_DEPRECATED);
		}
		
		public function getProfiles() {
			return null;
		}
		
	}
	
	
	class BigTreeGoogleAnalyticsProfile {
		
		public function __construct($profile, &$api) {
			trigger_error('BigTreeGoogleAnalyticsProfile is deprecated and no longer functional.', E_USER_DEPRECATED);
		}
		
	}
