<?php
	/*
		Class: BigTree\GooglePlus\Comment
			A Google+ object that contains information about and methods you can perform on a comment.
	*/

	namespace BigTree\GooglePlus;

	use stdClass;

	class Comment {

		/** @var \BigTree\GooglePlus\API */
		protected $API;

		function __construct($comment,&$api) {
			$this->API = $api;
			isset($comment->object->content) ? $this->Content = $comment->object->content : false;
			isset($comment->object->originalContent) ? $this->ContentPlainText = $comment->object->originalContent : false;
			isset($comment->published) ? $this->CreatedAt = date("Y-m-d H:i:s",strtotime($comment->published)) : false;
			isset($comment->id) ? $this->ID = $comment->id : false;
			isset($comment->totalItems) ? $this->PlusOneCount = $comment->plusoners->totalItems : false;
			if (is_array($comment->inReplyTo)) {
				$this->RepliedTo = array();
				foreach ($comment->inReplyTo as $reply) {
					$r = new stdClass;
					$r->ID = $reply->id;
					$r->URL = $reply->url;
					$this->RepliedTo[] = $r;
				}
			}
			isset($comment->verb) ? $this->Type = $comment->verb : false;
			isset($comment->updated) ? $this->UpdatedAt = date("Y-m-d H:i:s",strtotime($comment->updated)) : false;
			isset($comment->selfLink) ? $this->URL = $comment->selfLink : false;
			isset($comment->actor) ? $this->User = new Person($comment->actor,$api) : false;
		}

	}
