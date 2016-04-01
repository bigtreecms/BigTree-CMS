<?php
	/*
		Class: BigTree\Facebook\JobTitle
			Facebook API class for a job title.
	*/

	namespace BigTree\Facebook;

	class JobTitle {

		protected $API;

		function __construct($job,&$api) {
			$this->API = $api;

			$this->ID = $job->id;
			$this->Name = $job->name;
		}

	}
	