<?php
	/*
		Class: BigTree\Disqus\Post
			A Disqus object that contains information about and methods you can perform on a forum post.
	*/

	namespace BigTree\Disqus;

	class Post {

		protected $API;

		function __construct($post,&$api) {
			$this->API = $api;
			isset($post->isApproved) ? $this->Approved = $post->isApproved : false;
			isset($post->author) ? $this->Author = new User($post->author,$api) : false;
			isset($post->message) ? $this->Content = $post->message : false;
			isset($post->raw_message) ? $this->ContentPlainText = $post->raw_message : false;
			isset($post->isDeleted) ? $this->Deleted = $post->isDeleted : false;
			isset($post->dislikes) ? $this->Dislikes = $post->dislikes : false;
			isset($post->isEdited) ? $this->Edited = $post->isEdited : false;
			isset($post->isFlagged) ? $this->Flagged = $post->isFlagged : false;
			isset($post->isHighlighted) ? $this->Highlighted = $post->isHighlighted : false;
			isset($post->id) ? $this->ID = $post->id : false;
			isset($post->likes) ? $this->Likes = $post->likes : false;
			isset($post->media) ? $this->Media = $post->media : false;
			isset($post->parent) ? $this->ParentID = $post->parent : false;
			isset($post->points) ? $this->Points = $post->points : false;
			isset($post->numReports) ? $this->Reports = $post->numReports : false;
			isset($post->isSpam) ? $this->Spam = $post->isSpam : false;
			isset($post->thread) ? $this->ThreadID = $post->thread : false;
			isset($post->createdAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($post->createdAt)) : false;
			isset($post->userScore) ? $this->UserScore = $post->userScore : false;
		}

		function _cacheBust() {
			$this->API->cacheBust("threadposts".$this->ThreadID);
			$this->API->cacheBust("post".$this->ID);
		}

		/*
			Function: approve
				Approves this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function approve() {
			$response = $this->API->call("posts/approve.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: highlight
				Highlights this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function highlight() {
			$response = $this->API->call("posts/highlight.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: remove
				Removes this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function remove() {
			$response = $this->API->call("posts/remove.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: report
				Reports/flags this post.

			Returns:
				true if successful.
		*/

		function report() {
			$response = $this->API->call("posts/report.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: restore
				Restores this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function restore() {
			$response = $this->API->call("posts/restore.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: spam
				Marks this post as spam.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function spam() {
			$response = $this->API->call("posts/spam.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: unhighlight
				Unhighlights this post.
				Authenticated user must be a moderator of the forum this post is on.

			Returns:
				true if successful.
		*/

		function unhighlight() {
			$response = $this->API->call("posts/unhighlight.json",array("post" => $this->ID),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}

		/*
			Function: vote
				Causes the authenticated user to vote on a post.

			Parameters:
				vote - Vote to cast (-1, 0, or 1)

			Returns:
				true if successful.
		*/

		function vote($vote = 0) {
			$response = $this->API->call("posts/vote.json",array("post" => $this->ID,"vote" => $vote),"POST");

			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}

			return false;
		}
	}
