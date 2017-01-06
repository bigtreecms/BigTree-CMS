<?php
	/*
		Class: BigTree\Facebook\JobTitle
			Facebook API class for a job title.
	*/
	
	namespace BigTree\Facebook;
	
	use stdClass;
	
	class JobTitle {
		
		/** @var \BigTree\Facebook\API */
		protected $API;
		
		function __construct(stdClass $job, API &$api) {
			$this->API = $api;
			
			$this->ID = $job->id;
			$this->Name = $job->name;
		}
		
	}
	