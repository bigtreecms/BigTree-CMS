<?php
	/*
		Class: BigTree\Instagram\Comment
			An Instagram object that contains information about and methods you can perform on a comment.
	*/

	namespace BigTree\Instagram;

	class Comment {

		/** @var \BigTree\Instagram\API */
		protected $API;

		public $Content;
		public $ID;
		public $MediaID;
		public $Timestamp;
		public $User;

		/*
			Constructor:
				Creates a comment object from Instagram data.

			Parameters:
				comment - Instagram data
				media_id - ID for the media the comment was attached to
				api - Reference to the BigTree\Instagram\API class instance
		*/

		function __construct($comment,$media_id,&$api) {
			$this->API = $api;
			isset($comment->text) ? $this->Content = $comment->text : false;
			isset($comment->id) ? $this->ID = $comment->id : false;
			$this->MediaID = $media_id;
			isset($comment->created_time) ? $this->Timestamp = date("Y-m-d H:i:s",$comment->created_time) : false;
			isset($comment->from) ? $this->User = new User($comment->from,$api) : false;
		}

		/*
			Function: delete
				Deletes the comment (must belong to the authenticated user)

			Returns:
				true if successful
		*/

		function delete() {
			return $this->API->deleteComment($this->MediaID,$this->ID);
		}
		
	}