<?php
	/*
		Class: BigTree\Twitter\Place
			A Twitter object that contains information about and methods you can perform on a place.
	*/

	namespace BigTree\Twitter;

	class Place
	{

		/** @var API */
		protected $API;

		public $BoundingBox;
		public $Country;
		public $CountryCode;
		public $FullName;
		public $ID;
		public $Name;
		public $Type;
		public $URL;

		/*
			Constructor:
				Creates a place object from Twitter data.

			Parameters:
				place - Twitter data
				api - Reference to the BigTree\Twitter\API class instance
		*/

		public function __construct($place, API &$api)
		{
			$this->API = $api;
			isset($place->bounding_box->coordinates) ? $this->BoundingBox = $place->bounding_box->coordinates : false;
			isset($place->country) ? $this->Country = $place->country : false;
			isset($place->country_code) ? $this->CountryCode = $place->country_code : false;
			isset($place->full_name) ? $this->FullName = $place->full_name : false;
			isset($place->id) ? $this->ID = $place->id : false;
			isset($place->name) ? $this->Name = $place->name : false;
			isset($place->place_type) ? $this->Type = $place->place_type : false;
			isset($place->url) ? $this->URL = $place->url : false;
		}

		/*
			Function: __toString
				Returns the Places's name when this object is treated as a string.
		*/

		public function __toString(): string
		{
			return $this->Name;
		}

	}
