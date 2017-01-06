<?php
	
	/*
		Class: BigTree\GoogleAnalytics\Profile
			A Google Analytics object that contains information about and methods you can perform on a profile.
	*/
	
	namespace BigTree\GoogleAnalytics;
	
	use stdClass;
	
	class Profile {
		
		protected $API;
		
		public $AccountID;
		public $CreatedAt;
		public $Currency;
		public $ID;
		public $Name;
		public $PropertyID;
		public $Timezone;
		public $Type;
		public $UpdatedAt;
		public $WebsiteURL;
		
		function __construct(stdClass $profile, API &$api) {
			$this->AccountID = $profile->accountId;
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s", strtotime($profile->created));
			$this->Currency = $profile->currency;
			$this->ID = $profile->id;
			$this->Name = $profile->name;
			$this->PropertyID = $profile->webPropertyId;
			$this->Timezone = $profile->timezone;
			$this->Type = $profile->type;
			$this->UpdatedAt = date("Y-m-d H:i:s", strtotime($profile->updated));
			$this->WebsiteURL = $profile->websiteUrl;
		}
		
	}
	