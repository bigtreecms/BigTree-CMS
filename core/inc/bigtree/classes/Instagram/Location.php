<?php
	/*
		Class: BigTree\Instagram\Location
			An Instagram object that contains information about and methods you can perform on a location.
	*/

	namespace BigTree\Instagram;

	class Location {

		/** @var \BigTree\Instagram\API */
		protected $API;

		public $ID;
		public $Latitude;
		public $Longitude;
		public $Name;

		/*
			Constructor:
				Creates a location object from Instagram data.

			Parameters:
				location - Instagram data
				api - Reference to the BigTree\Instagram\API class instance
		*/

		function __construct($location,$api) {
			$this->API = $api;
			isset($location->id) ? $this->ID = $location->id : false;
			isset($location->latitude) ? $this->Latitude = $location->latitude : false;
			isset($location->longitude) ? $this->Longitude = $location->longitude : false;
			isset($location->name) ? $this->Name = $location->name : false;
		}

		/*
			Function: getMedia
				Alias for BigTree\Instagram\API::getLocationMedia
		*/

		function getMedia() {
			return $this->API->getLocationMedia($this->ID);
		}

	}