<?php

	/*
		Class: BigTree\GoogleAnalytics\Profile
			A Google Analytics object that contains information about and methods you can perform on a profile.
	*/

	namespace BigTree\GoogleAnalytics;

	class Profile {
		protected $API;

		function __construct($profile,&$api) {
			$this->AccountID = $profile->accountId;
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($profile->created));
			$this->Currency = $profile->currency;
			$this->ID = $profile->id;
			$this->Name = $profile->name;
			$this->PropertyID = $profile->webPropertyId;
			$this->Timezone = $profile->timezone;
			$this->Type = $profile->type;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($profile->updated));
			$this->WebsiteURL = $profile->websiteUrl;
		}
	}
	