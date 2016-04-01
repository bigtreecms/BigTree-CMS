<?php
	/*
		Class: BigTree\Facebook\School
			Facebook API class for a school.
	*/

	namespace BigTree\Facebook;

	class School {

		/** @var \BigTree\Facebook\API */
		protected $API;

		function __construct($school,$type,&$api) {
			$this->API = $api;

			$this->ID = $school->id;
			$this->Name = $school->name;
			$this->Type = $type;
		}

	}
	