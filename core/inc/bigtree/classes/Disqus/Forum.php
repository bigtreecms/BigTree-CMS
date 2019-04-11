<?php
	/*
		Class: BigTree\Disqus\Forum
			A Disqus object that contains information about and methods you can perform on a forum.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class Forum
	{
		
		/** @var API */
		protected $API;
		
		public $FounderID;
		public $ID;
		public $Image;
		public $Language;
		public $Name;
		public $Settings;
		public $URL;
		
		function __construct(stdClass $forum, API &$api)
		{
			$this->API = $api;
			isset($forum->founder) ? $this->FounderID = $forum->founder : false;
			isset($forum->id) ? $this->ID = $forum->id : false;
			isset($forum->favicon->permalink) ? $this->Image = $forum->favicon->permalink : false;
			isset($forum->language) ? $this->Language = $forum->language : false;
			isset($forum->name) ? $this->Name = $forum->name : false;
			isset($forum->settings) ? $this->Settings = $forum->settings : false;
			isset($forum->url) ? $this->URL = $forum->url : false;
		}
		
		/*
			Function: addCategory
				Adds a category to this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				title - The title of this category.

			Returns:
				A BigTree\Disqus\Category object.
		*/
		
		function addCategory(string $title): ?Category
		{
			$response = $this->API->call("categories/create.json", ["forum" => $this->ID, "title" => $title], "POST");
			
			if (!empty($response)) {
				$this->API->cacheBust("categories".$this->ID);
				
				return new Category($response, $this->API);
			}
			
			return null;
		}
		
		/*
			Function: addModerator
				Adds a moderator to this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				user - The ID of the user or the person's username
		*/
		
		function addModerator(string $user): ?bool
		{
			$params = ["forum" => $this->ID];
			
			if (is_numeric($user)) {
				$params["user"] = $user;
			} else {
				$params["user:username"] = $user;
			}
			
			$response = $this->API->call("forums/addModerator.json", $params, "POST");
			
			if (!empty($response)) {
				$this->API->cacheBust("moderators".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: addToBlacklist
				Adds an entry to this forum's blacklist

			Parameters:
				type - Type of entry (word, ip, user, email)
				value - Value to block
				retroactive - Whether to make this block affect old posts (defaults to false)
				notes - Notes (optional)
		*/
		
		function addToBlacklist(string $type, string $value, bool $retroactive = false, string $notes = ""): bool
		{
			$response = $this->API->call("blacklists/add.json",
										 ["forum" => $this->ID, $type => $value, "retroactive" => $retroactive, "notes" => $notes],
										 "POST");
			
			if (!is_null($response)) {
				$this->API->cacheBust("blacklisted".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: addToWhitelist
				Adds an entry to this forum's whitelist

			Parameters:
				type - Type of entry (email,user_id,username)
				value - Value to whitelist
				notes - Notes (optional)
		*/
		
		function addToWhitelist(string $type, string $value, string $notes = ""): bool
		{
			$params = ["forum" => $this->ID, "notes" => $notes];
			
			if ($type == "email") {
				$params["email"] = $value;
			} elseif ($type == "user_id") {
				$params["user"] = $value;
			} elseif ($type == "username") {
				$params["user:username"] = $value;
			}
			
			$response = $this->API->call("whitelists/add.json", $params, "POST");
			
			if (!is_null($response)) {
				$this->API->cacheBust("whitelisted".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: getBlacklist
				Returns a result set of blacklist entries for this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				limit - Number of entries per page (defaults to 25, max 100)
				order - Sort order (asc or desc, defaults to asc)
				params - Additional parameters to send to blacklists/list API call.

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\BlacklistEntry objects
		*/
		
		function getBlacklist(int $limit = 25, string $order = "asc", array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("blacklists/list.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("blacklisted".$this->ID);
				$results = [];
				
				foreach ($response->Results as $item) {
					$this->API->cachePush("blacklist".$item->id);
					$results[] = new BlacklistEntry($item, $this->API);
				}
				
				return new ResultSet($this, "getBlacklist", [$limit, $order, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getCategories
				Returns categories for this forum.

			Parameters:
				limit - Number of categories to return per page (defaults to 25, max 100)
				order - Sort order (asc or desc, default asc)
				params - Additional parameters to send to forums/listCategories API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\Category objects.
		*/
		
		function getCategories(int $limit = 25, string $order = "asc", array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("forums/listCategories.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("categories".$this->ID);
				$results = [];
				
				foreach ($response->Results as $category) {
					$this->API->cachePush("category".$category->id);
					$results[] = new Category($category, $this->API);
				}
				
				return new ResultSet($this, "getCategories", [$limit, $order, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getFounder
				Returns information about this forum's founder.

			Returns:
				A BigTree\Disqus\User object.
		*/
		
		function getFounder(): ?User
		{
			return $this->API->getUser($this->FounderID);
		}
		
		/*
			Function: getModerators
				Returns an array of moderators for this forum.

			Returns:
				An array of BigTree\Disqus\User objects.
		*/
		
		function getModerators(): ?array
		{
			$response = $this->API->call("forums/listModerators.json", ["forum" => $this->ID]);
			
			if (is_array($response)) {
				$this->API->cachePush("moderators".$this->ID);
				$results = [];
				
				foreach ($response as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return $results;
			}
			
			return null;
		}
		
		/*
			Function: getMostActiveUsers
				Returns a result set of most active users on this forum.

			Parameters:
				limit - Number of users to return per page (defaults to 25, max 100)
				params - Additional parameters to send to forums/listMostActiveUsers API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\User objects.
		*/
		
		function getMostActiveUsers(int $limit = 25, array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listMostActiveUsers.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return new ResultSet($this, "getMostActiveUsers", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getMostLikedUsers
				Returns a result set of the most liked users on this forum.

			Parameters:
				limit - Number of users to return per page (defaults to 25, max 100)
				params - Additional parameters to send to forums/listMostActiveUsers API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\User objects.
		*/
		
		function getMostLikedUsers(int $limit = 25, array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listMostLikedUsers.json", $params);
			
			if (!empty($response)) {
				$results = [];
				
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return new ResultSet($this, "getMostLikedUsers", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getPosts
				Returns a result set of posts to this forum.

			Parameters:
				limit - Number of posts to return (max 100, default 25)
				order - Sort order (asc or desc, defaults to desc)
				include - Array of post types to include (options are unapproved,approved,spam,deleted,flagged — defaults to approved)
				since - Unix timestamp that indicates to return only posts occurring after this timestamp.
				params - Additional parameters to send to the forums/listPosts API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\Post objects.
		*/
		
		function getPosts(int $limit = 25, string $order = "desc", array $include = ["approved"], bool $since = false,
						  array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["include"] = $include;
			
			if ($since) {
				$params["since"] = $since;
			}
			
			$response = $this->API->call("forums/listPosts.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("forumposts".$this->ID);
				$results = [];
				
				foreach ($response->Results as $post) {
					$this->API->cachePush("post".$post->id);
					$results[] = new Post($post, $this->API);
				}
				
				return new ResultSet($this, "getPosts", [$limit, $order, $include, $since, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getThreads
				Returns a result set of threads in this forum.

			Parameters:
				limit - Number of threads to return (max 100, default 25)
				order - Sort order (asc or desc, defaults to desc)
				since - Unix timestamp that indicates to return only threads occurring after this timestamp.
				params - Additional parameters to send to the forums/listThreads API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\Thread objects.
		*/
		
		function getThreads(int $limit = 25, string $order = "desc", ?string $since = null,
							array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			
			if ($since) {
				$params["since"] = $since;
			}
			
			$response = $this->API->call("forums/listThreads.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("threads".$this->ID);
				$results = [];
				
				foreach ($response->Results as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new Thread($thread, $this->API);
				}
				
				return new ResultSet($this, "getThreads", [$limit, $order, $since, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getTrendingThreads
				Returns an array of trending threads in this forum.

			Parameters:
				limit - Number of threads to return (max 10, default 10)

			Returns:
				An array of BigTree\Disqus\Post objects.
		*/
		
		function getTrendingThreads(int $limit = 10): ?array
		{
			$response = $this->API->call("trends/listThreads.json", ["forum" => $this->ID, "limit" => $limit]);
			
			if (is_array($response)) {
				$results = [];
				
				foreach ($response as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new Thread($thread, $this->API);
				}
				
				return $results;
			}
			
			return null;
		}
		
		/*
			Function: getUsers
				Returns a result set of users of this forum.

			Parameters:
				limit - Number of users to return (max 100, default 25)
				params - Additional parameters to send to the forums/listUsers API call

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\User objects.
		*/
		
		function getUsers(int $limit = 25, array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listUsers.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("users".$this->ID);
				$results = [];
				
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new User($user, $this->API);
				}
				
				return new ResultSet($this, "getUsers", [$limit, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: getWhitelist
				Returns a result set of whitelist entries for this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				limit - Number of entries per page (defaults to 25, max 100)
				order - Sort order (asc or desc, defaults to asc)
				params - Additional parameters to send to blacklists/list API call.

			Returns:
				A BigTree\Disqus\ResultSet of BigTree\Disqus\WhitelistEntry objects
		*/
		
		function getWhitelist(int $limit = 25, string $order = "asc", array $params = []): ?ResultSet
		{
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("whitelists/list.json", $params);
			
			if (!empty($response)) {
				$this->API->cachePush("whitelisted".$this->ID);
				$results = [];
				
				foreach ($response->Results as $item) {
					$this->API->cachePush("whitelist".$item->id);
					$results[] = new WhitelistEntry($item, $this->API);
				}
				
				return new ResultSet($this, "getWhitelist", [$limit, $order, $params], $response->Cursor, $results);
			}
			
			return null;
		}
		
		/*
			Function: removeModerator
				Removes a moderator to this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				user - The ID of the user or the person's username
		*/
		
		function removeModerator(string $user): bool
		{
			$params = ["forum" => $this->ID];
		
			if (is_numeric($user)) {
				$params["user"] = $user;
			} else {
				$params["user:username"] = $user;
			}
			
			$response = $this->API->call("forums/removeModerator.json", $params, "POST");
			
			if (!empty($response)) {
				$this->API->cacheBust("moderators".$this->ID);
				
				return true;
			}
			
			return false;
		}
	}
	