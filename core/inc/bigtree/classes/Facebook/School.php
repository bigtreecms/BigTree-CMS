<?php
	/*
		Class: BigTree\Facebook\School
			Facebook API class for a school.
	*/
	
	namespace BigTree\Facebook;
	
	use stdClass;
	
	class School {
		
		/** @var \BigTree\Facebook\API */
		protected $API;
		
		public $ID;
		public $Name;
		public $Type;
		
		function __construct(stdClass $school, string $type, API &$api) {
			$this->API = $api;
			
			$this->ID = $school->id;
			$this->Name = $school->name;
			$this->Type = $type;
		}
		
	}
	