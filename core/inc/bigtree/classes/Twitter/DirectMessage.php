<?php
	/*
		Class: BigTree\Twitter\DirectMessage
			A Twitter object that contains information about and methods you can perform on a direct message.
	*/

	namespace BigTree\Twitter;

	class DirectMessage {

		/** @var \BigTree\Twitter\API */
		protected $API;

		public $Content;
		public $ID;
		public $LinkedContent;
		public $Recipient;
		public $Sender;
		public $Timestamp;

		/*
			Constructor:
				Create a direct message object from Twitter data.

			Parameters:
				message - Twitter data
				api - Reference to BigTree\Twitter\API class instance
		*/

		function __construct($message,&$api) {
			$this->API = $api;
			isset($message->text) ? $this->Content = $message->text : false;
			isset($message->id) ? $this->ID = $message->id : false;
			isset($message->text) ? $this->LinkedContent = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://search.twitter.com/search?q=%23\2" target="_blank">#\2</a>',preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>',preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@",'<a href="\0" target="_blank">\0</a>',$message->text))) : false;
			isset($message->recipient) ? $this->Recipient = new User($message->recipient,$api) : false;
			isset($message->sender) ? $this->Sender = new User($message->sender,$api) : false;
			isset($message->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($message->created_at)) : false;
		}

		/*
			Function: __toString
				Returns the Message's content when this object is treated as a string.
		*/

		function __toString() {
			return $this->Content;
		}

		/*
			Function: delete
				Alias for BigTree\Twitter\Tweet::deleteDirectMessage
		*/

		function delete() {
			return $this->API->deleteDirectMessage($this->ID);
		}

		/*
			Function: reply
				Alias for BigTree\Twitter\Tweet::sendDirectMessage
		*/

		function reply($content) {
			return $this->API->sendDirectMessage(false,$content,$this->Sender->ID);
		}

	}