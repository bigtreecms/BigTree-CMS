<?php
	
	/*
		Class: BigTree\GoogleAnalytics\Account
			A Google Analytics object that contains information about and methods you can perform on an account.
	*/
	
	namespace BigTree\GoogleAnalytics;
	
	class Account {
		
		/** @var \BigTree\GoogleAnalytics\API */
		protected $API;
		
		public $CreatedAt;
		public $ID;
		public $Name;
		public $UpdatedAt;
		
		function __construct($account, &$api) {
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s", strtotime($account->created));
			$this->ID = $account->id;
			$this->Name = $account->name;
			$this->UpdatedAt = date("Y-m-d H:i:s", strtotime($account->updated));
		}
		
		function getProperties($params) {
			return $this->API->getProperties($this->ID, $params);
		}
		
	}
	