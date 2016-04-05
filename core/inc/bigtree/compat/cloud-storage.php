<?php
	// Backwards compatibility class.
	class BigTreeCloudStorage {

		protected $API;

		function __construct($service = false) {
			$settings = BigTree\Setting::value("bigtree-internal-cloud-storage");

			if ($settings["service"] == "amazon") {
				$this->API = new BigTree\CloudStorage\Amazon;
			} elseif ($settings["service"] == "google") {
				$this->API = new BigTree\CloudStorage\Google;
			} elseif ($settings["service"] == "rackspace") {
				$this->API = new BigTree\CloudStorage\Rackspace;
			}
		}

		// Magic method to intercept calls and route them to the API
		function __call($method, $arguments) {
			return call_user_func_array(array($this->API,$method), $arguments);
		}

	}
	