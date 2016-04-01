<?php
	/*
		Class: BigTree\Disqus\WhitelistEntry
			A Disqus object that contains information about and methods you can perform on a whitelist entry.
	*/

	namespace BigTree\Disqus;

	class WhitelistEntry {

		protected $API;

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
				Removes this whitelist entry.
		*/

		function remove() {
			$response = $this->API->call("whitelists/remove.json",array("forum" => $this->ForumID,$this->Type => $this->Value),"POST");

			if ($response !== false) {
				$this->API->cacheBust("whitelisted".$this->ForumID);
				$this->API->cacheBust("whitelist".$this->ID);
				return true;
			}

			return false;
		}

	}