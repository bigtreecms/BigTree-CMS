<?php
	/*
		Class: BigTree\Facebook\Location
			Facebook API class for a location.
	*/

	namespace BigTree\Facebook;

	class Location {

		/** @var \BigTree\Facebook\API */
		protected $API;

		function __construct($location,&$api) {
			$this->API = $api;

			$this->ID = $location->id;
			$this->Name = $location->name;
		}

	}
	