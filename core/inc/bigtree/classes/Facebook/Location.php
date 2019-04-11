<?php
	/*
		Class: BigTree\Facebook\Location
			Facebook API class for a location.
	*/
	
	namespace BigTree\Facebook;
	
	use stdClass;
	
	class Location
	{
		
		/** @var API */
		protected $API;
		
		public $ID;
		public $Name;
		
		public function __construct(stdClass $location, API &$api)
		{
			$this->API = $api;
			
			$this->ID = $location->id;
			$this->Name = $location->name;
		}
		
	}
	