<?php
	/*
		Class: BigTree\Disqus\User
			A Disqus object that contains information about and methods you can perform on a user.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class User
	{
		
		/** @var API */
		protected $API;
		
		public $Anonymous;
		public $Description;
		public $Email;
		public $ID;
		public $FollowerCount;
		public $FollowingCount;
		public $Image;
		public $LikesCount;
		public $Location;
		public $Name;
		public $PostCount;
		public $Primary;
		public $ProfileURL;
		public $Reputation;
		public $Timestamp;
		public $URL;
		public $Verified;
		
		function __construct(stdClass $user, API &$api)
		{
			$this->API = $api;
			isset($user->isAnonymous) ? $this->Anonymous = $user->isAnonymous : false;
			isset($user->about) ? $this->Description = $user->about : false;
			isset($user->email) ? $this->Email = $user->email : false;
			isset($user->id) ? $this->ID = $user->id : false;
			isset($user->numFollowers) ? $this->FollowerCount = $user->numFollowers : false;
			isset($user->numFollowing) ? $this->FollowingCount = $user->numFollowing : false;
			isset($user->avatar->permalink) ? $this->Image = $user->avatar->permalink : false;
			isset($user->numLikesReceived) ? $this->LikesCount = $user->numLikesReceived : false;
			isset($user->location) ? $this->Location = $user->location : false;
			isset($user->name) ? $this->Name = $user->name : false;
			isset($user->numPosts) ? $this->PostCount = $user->numPosts : false;
			isset($user->isPrimary) ? $this->Primary = $user->isPrimary : false;
			isset($user->profileUrl) ? $this->ProfileURL = $user->profileUrl : false;
			isset($user->rep) ? $this->Reputation = $user->rep : false;
			isset($user->joinedAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($user->joinedAt)) : false;
			isset($user->url) ? $this->URL = $user->url : false;
			isset($user->isVerified) ? $this->Verified = $user->isVerified : false;
		}
		
		/*
			Function: getActiveForums
				Returns a result set of forums this user is active in.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listActiveForums API call
		*/
		
		function getActiveForums(int $limit = 25, array $params = []): ?ResultSet
		{
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listActiveForums.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $forum) {
					$this->API->cachePush("forum".$forum->id);
					$results[] = new Forum($forum, $this->API);
				}
				
				return new ResultSet($this, "getActiveForums", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getActiveThreads
				Returns a result set of threads this user is active in.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listActiveForums API call
		*/
		
		function getActiveThreads(int $limit = 25, array$params = []): ?ResultSet
		{
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listActiveThreads.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new Thread($thread, $this->API);
				}
				
				return new ResultSet($this, "getActiveThreads", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getFollowers
				Returns a result set of users that follow this user.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listFollowers API call
		*/
		
		function getFollowers(int $limit = 25, array$params = []): ?ResultSet
		{
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listFollowers.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return new ResultSet($this, "getFollowers", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getFollowing
				Returns a result set of users that this user follows.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listFollowing API call
		*/
		
		function getFollowing(int $limit = 25, array$params = []): ?ResultSet
		{
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listFollowing.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return new ResultSet($this, "getFollowing", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getPosts
				Returns a result set of posts by this user.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				order - Sort order (asc or desc, defaults to desc)
				params - Additional parameters to send to users/listPosts API call
		*/
		
		function getPosts(int $limit = 25, string $order = "desc", array $params = []): ?ResultSet
		{
			$params["limit"] = $limit;
			$params["order"] = $order;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listPosts.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("userposts".$this->ID);
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
			Function: follow
				Causes the authenticated user to follow this user.

			Returns:
				true if successful.
		*/
		
		function follow(): bool
		{
			$response = $this->API->call("users/follow.json", ["target" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->API->cacheBust("user".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: unfollow
				Causes the authenticated user to unfollow this user.

			Returns:
				true if successful.
		*/
		
		function unfollow(): bool
		{
			$response = $this->API->call("users/unfollow.json", ["target" => $this->ID], "POST");
			
			if (!empty($response)) {
				$this->API->cacheBust("user".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
	}