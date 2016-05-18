<?php
	/*
		Class: BigTree\Disqus\BlacklistEntry
			A Disqus object that contains information about and methods you can perform on a blacklist entry.
	*/

	namespace BigTree\Disqus;

	class BlacklistEntry {

		/** @var \BigTree\Disqus\API */
		protected $API;

		public $ForumID;
		public $ID;
		public $Notes;
		public $Timestamp;
		public $Type;
		public $Value;

		function __construct($item,&$api) {
			$this->API = $api;
			$this->ForumID = $item->forum;
			$this->ID = $item->id;
			$this->Notes = $item->notes;
			$this->Timestamp = date("Y-m-d H:i:s",strtotime($item->createdAt));
			$this->Type = $item->type;
			$this->Value = $item->value;
		}

		/*
			Function: remove
				Removes this blacklist entry.
		*/

		function remove() {
			$response = $this->API->call("blacklists/remove.json",array("forum" => $this->ForumID,$this->Type => $this->Value),"POST");
			if ($response !== false) {
				$this->API->cacheBust("blacklist".$this->ID);
				$this->API->cacheBust("blacklisted".$this->ForumID);
				return true;
			}
			return false;
		}
		
	}
