<?php
	// Backwards compatibility class.
	class BigTreeCloudStorage {

		protected $API;

		public $Connected;
		public $Settings;

		function __construct($service = false) {
			$this->Settings = BigTree\Setting::value("bigtree-internal-cloud-storage");

			if ($this->Settings["service"] == "amazon") {
				$this->API = new BigTree\CloudStorage\Amazon;
			} elseif ($this->Settings["service"] == "google") {
				$this->API = new BigTree\CloudStorage\Google;
			} elseif ($this->Settings["service"] == "rackspace") {
				$this->API = new BigTree\CloudStorage\Rackspace;
			}

			$this->Connected = $this->API->Active;
		}

		// Magic method to intercept calls and route them to the API
		function __call($method, $arguments) {
			return call_user_func_array(array($this->API,$method), $arguments);
		}

	}
	