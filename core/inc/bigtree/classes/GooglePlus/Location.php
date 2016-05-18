<?php
	/*
		Class: BigTree\GooglePlus\Location
			A Google+ object that contains information about and methods you can perform on a location.
	*/

	namespace BigTree\GooglePlus;

	class Location {

		/** @var \BigTree\GooglePlus\API */
		protected $API;

		public $Address;
		public $Latitude;
		public $Longitude;
		public $Name;

		function __construct($location,&$api) {
			$this->API = $api;
			isset($location->address->formatted) ? $this->Address = $location->address->formatted : false;
			isset($location->position->latitude) ? $this->Latitude = $location->position->latitude : false;
			isset($location->position->longitude) ? $this->Longitude = $location->position->longitude : false;
			isset($location->displayName) ? $this->Name = $location->displayName : false;
		}

	}
	