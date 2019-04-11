<?php
	
	/*
		Class: BigTree\GoogleAnalytics\Account
			A Google Analytics object that contains information about and methods you can perform on an account.
	*/
	
	namespace BigTree\GoogleAnalytics;
	
	use BigTree\GoogleResultSet;
	use stdClass;
	
	class Account
	{
		
		/** @var API */
		protected $API;
		
		public $CreatedAt;
		public $ID;
		public $Name;
		public $UpdatedAt;
		
		function __construct(stdClass $account, API &$api)
		{
			$this->API = $api;
			$this->CreatedAt = date("Y-m-d H:i:s", strtotime($account->created));
			$this->ID = $account->id;
			$this->Name = $account->name;
			$this->UpdatedAt = date("Y-m-d H:i:s", strtotime($account->updated));
		}
		
		function getProperties(array $params): ?GoogleResultSet
		{
			return $this->API->getProperties($this->ID, $params);
		}
		
	}
	