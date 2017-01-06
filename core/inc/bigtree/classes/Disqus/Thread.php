<?php
	/*
		Class: BigTree\Disqus\Thread
			A Disqus object that contains information about and methods you can perform on a forum thread.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class Thread {
		
		/** @var \BigTree\Disqus\API */
		protected $API;
		
		public $AuthorID;
		public $CategoryID;
		public $Closed;
		public $Deleted;
		public $Dislikes;
		public $Feed;
		public $ForumID;
		public $ID;
		public $Identifiers;
		public $Likes;
		public $Message;
		public $PostCount;
		public $Reactions;
		public $Slug;
		public $Subscribed;
		public $Timestamp;
		public $Title;
		public $URL;
		public $UserScore;
		
		function __construct(stdClass $thread, API &$api) {
			$this->API = $api;
			isset($thread->author) ? $this->AuthorID = $thread->author : false;
			isset($thread->category) ? $this->CategoryID = $thread->category : false;
			isset($thread->isClosed) ? $this->Closed = $thread->isClosed : false;
			isset($thread->isDeleted) ? $this->Deleted = $thread->isDeleted : false;
			isset($thread->dislikes) ? $this->Dislikes = $thread->dislikes : false;
			isset($thread->feed) ? $this->Feed = $thread->feed : false;
			isset($thread->forum) ? $this->ForumID = $thread->forum : false;
			isset($thread->id) ? $this->ID = $thread->id : false;
			isset($thread->identifiers) ? $this->Identifiers = $thread->identifiers : false;
			isset($thread->likes) ? $this->Likes = $thread->likes : false;
			isset($thread->message) ? $this->Message = $thread->message : false;
			isset($thread->posts) ? $this->PostCount = $thread->posts : false;
			isset($thread->reactions) ? $this->Reactions = $thread->reactions : false;
			isset($thread->slug) ? $this->Slug = $thread->slug : false;
			isset($thread->userSubscription) ? $this->Subscribed = $thread->userSubscription : false;
			isset($thread->createdAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($thread->createdAt)) : false;
			isset($thread->title) ? $this->Title = $thread->title : false;
			isset($thread->link) ? $this->URL = $thread->link : false;
			isset($thread->userScore) ? $this->UserScore = $thread->userScore : false;
		}
		
		private function _cacheBust() {
			$this->API->cacheBust("forumthreads".$this->ForumID);
			$this->API->cacheBust("thread".$this->ID);
		}
		
		/*
			Function: close
				Closes this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/
		
		function close(): bool {
			$response = $this->API->call("threads/close.json", ["thread" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: getPosts
				Returns a result set of posts in this thread.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				order - Sort order (asc or desc, defaults to desc)
				params - Additional parameters to send to threads/listPosts API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\Post objects.
		*/
		
		function getPosts(int $limit = 25, string $order = "desc", array $params = []): ?ResultSet {
			$params["thread"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("threads/listPosts.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("threadposts".$this->ID);
				$results = [];
				
				foreach ($response->Results as $post) {
					$this->API->cachePush("post".$post->id);
					$results[] = new Post($post, $this->API);
				}
				
				return new ResultSet($this, "getPosts", [$limit, $order, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: open
				Opens this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/
		
		function open(): bool {
			$response = $this->API->call("threads/open.json", ["thread" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: remove
				Removes this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/
		
		function remove(): bool {
			$response = $this->API->call("threads/remove.json", ["thread" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: restore
				Restores this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/
		
		function restore(): bool {
			$response = $this->API->call("threads/restore.json", ["thread" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: subscribe
				Subscribes the authenticated user to this thread.

			Parameters:
				email - Email address to use for subscription (optional)
		*/
		
		function subscribe(?string $email = null): bool {
			$params = ["thread" => $this->ID];
			
			if ($email) {
				$params["email"] = $email;
			}
			
			$response = $this->API->call("threads/subscribe.json", $params, "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: unsubscribe
				Unsubscribes the authenticated user to this thread.

			Parameters:
				email - Email address used for subscription (optional)
		*/
		
		function unsubscribe(?string $email = null): bool {
			$params = ["thread" => $this->ID];
			
			if ($email) {
				$params["email"] = $email;
			}
			
			$response = $this->API->call("threads/unsubscribe.json", $params, "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: vote
				CAuses the authenticated user to set a vote on this thread.

			Parameters:
				vote - Vote to cast (-1, 0, or 1)
		*/
		
		function vote(int $vote = 0): bool {
			$response = $this->API->call("threads/vote.json", ["thread" => $this->ID, "vote" => $vote], "POST");
			
			if (!empty($response)) {
				$this->_cacheBust();
				
				return true;
			}
			
			return false;
		}
		
	}
