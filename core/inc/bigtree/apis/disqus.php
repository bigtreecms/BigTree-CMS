<?
	/*
		Class: BigTreeDisqusAPI
			The main Disqus API class used to retrieve lower level Disqus objects.
	*/

	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));

	class BigTreeDisqusAPI extends BigTreeOAuthAPIBase {

		var $AuthorizeURL = "https://disqus.com/api/oauth/2.0/authorize/";
		var $EndpointURL = "https://disqus.com/api/3.0/";
		var $OAuthVersion = "1.0";
		var $RequestType = "custom";
		var $Scope = "read,write,admin";
		var $TokenURL = "https://disqus.com/api/oauth/2.0/access_token/";
		
		/*
			Constructor:
				Sets up the Disqus API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-disqus-api","Disqus API","org.bigtreecms.api.disqus",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/disqus/return/";

			// Just send the request with the secret.
			$this->RequestParameters = array();
			$this->RequestParameters["access_token"] = &$this->Settings["token"];
			$this->RequestParameters["api_key"] = &$this->Settings["key"];
			$this->RequestParameters["api_secret"] = &$this->Settings["secret"];
		}

		/*
			Function: callUncached
				Wrapper for better Disqus error handling.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			$response = parent::callUncached($endpoint,$params,$method,$headers);
			if ($response->code != 0) {
				$this->Errors[] = $response->response;
				return false;
			}
			if (isset($response->cursor)) {
				$r = new stdClass;
				$cursor = new stdClass;
				$response->cursor->next ? $cursor->Next = $response->cursor->next : false;
				$response->cursor->prev ? $cursor->Previous = $response->cursor->prev : false;
				$response->cursor->total ? $cursor->Total = $response->cursor->total : false;
				$r->Cursor = $cursor;
				$r->Results = $response->response;
				return $r;
			}
			return $response->response;
		}

		/*
			Function: changeUsername
				Changes the username of the authenticated user.

			Parameters:
				username - The desired new username.

			Returns:
				true if successful
		*/

		function changeUsername($username) {
			$response = $this->call("users/checkUsername.json",array("username" => $username),"POST");
			if ($response) {
				return true;
			}
			return false;
		}

		/*
			Function: createForum
				Creates a new forum.

			Parameters:
				shortname - The shortname (unique) for the forum
				name - A name for the forum
				url - The URL the forum will be located at

			Returns:
				A BigTreeDisqusForum object.
				Returns false if the shortname is already taken.
		*/

		function createForum($shortname,$name,$url) {
			$response = $this->call("forums/create.json",array("website" => $url,"name" => $name,"short_name" => $shortname),"POST");
			if ($response !== false) {
				return new BigTreeDisqusForum($response,$this);
			}
		}

		/*
			Function: getCategory
				Returns a BigTreeDisqusCategory object for the given category id.

			Parameters:
				id - The category id

			Returns:
				A BigTreeDisqusCategory object.
		*/

		function getCategory($id) {
			$response = $this->call("categories/details.json",array("category" => $id));
			if ($response !== false) {
				$this->cachePush("category".$response->id);
				return new BigTreeDisqusCategory($response,$this);
			}
		}

		/*
			Function: getForum
				Returns a BigTreeDisqusForum object for the given forum shortname.

			Parameters:
				shortname - The forum shortname

			Returns:
				A BigTreeDisqusForum object.
		*/

		function getForum($shortname) {
			$response = $this->call("forums/details.json",array("forum" => $shortname));
			if ($response !== false) {
				$this->cachePush("forum".$response->id);
				return new BigTreeDisqusForum($response,$this);
			}
		}

		/*
			Function: getPost
				Returns a BigTreeDisqusPost object for the given post ID.

			Parameters:
				post - The post ID

			Returns:
				A BigTreeDisqusPost object.
		*/

		function getPost($id) {
			$response = $this->call("posts/details.json",array("post" => $id));
			if ($response !== false) {
				$this->cachePush("post".$response->id);
				return new BigTreeDisqusPost($response,$this);
			}
		}

		/*
			Function: getThread
				Returns a BigTreeDisqusThread object for the given thread ID.

			Parameters:
				thread - The thread ID, identifier, or link
				forum - If looking up by link, the shortname for the forum is required.

			Returns:
				A BigTreeDisqusThread object.
		*/

		function getThread($thread,$forum = false) {
			$params = array();
			if (!is_numeric($thread)) {
				if (substr($thread,0,7) == "http://" || substr($thread,0,8) == "https://") {
					$params["thread:link"] = $thread;
					$params["forum"] = $forum;
				} else {
					$params["thread:ident"] = $thread;
				}
			} else {
				$params["thread"] = $thread;
			}
			$response = $this->call("threads/details.json",$params);
			if ($response !== false) {
				$this->cachePush("thread".$response->id);
				return new BigTreeDisqusThread($response,$this);
			}
		}

		/*
			Function: getUser
				Returns a BigTreeDisqusUser object for the given user.
				If no user is passed in, the authenticated user's information is returned.

			Parameters:
				user - The ID of the user or the person's username (leave blank to use the authenticated user)

			Returns:
				A BigTreeDisqusUser object.
		*/

		function getUser($user = false) {
			$params = array();
			if (is_numeric($user)) {
				$params["user"] = $user;
			} elseif ($user) {
				$params["user:username"] = $user;
			}
			$response = $this->call("users/details.json",$params);
			if ($response !== false) {
				$this->cachePush("user".$response->id);
				return new BigTreeDisqusUser($response,$this);
			}
		}
	}

	/*
		Class: BigTreeDisqusBlacklistEntry
			A Disqus object that contains information about and methods you can perform on a blacklist entry.
	*/

	class BigTreeDisqusBlacklistEntry {
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

	/*
		Class: BigTreeDisqusCategory
			A Disqus object that contains information about and methods you can perform on a category.
	*/

	class BigTreeDisqusCategory {
		protected $API;

		function __construct($category,&$api) {
			$this->API = $api;
			isset($category->isDefault) ? $this->Default = $category->isDefault : false;
			isset($category->forum) ? $this->Forum = $category->forum : false;
			isset($category->id) ? $this->ID = $category->id : false;
			isset($category->title) ? $this->Name = $category->title : false;
			isset($category->order) ? $this->Order = $category->order : false;
		}
	}

	/*
		Class: BigTreeDisqusForum
			A Disqus object that contains information about and methods you can perform on a forum.
	*/

	class BigTreeDisqusForum {
		protected $API;

		function __construct($forum,&$api) {
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
				A BigTreeDisqusCategory object.
		*/

		function addCategory($title) {
			$response = $this->API->call("categories/create.json",array("forum" => $this->ID,"title" => $title),"POST");
			if ($response !== false) {
				$this->API->cacheBust("categories".$this->ID);
				return new BigTreeDisqusCategory($response,$this->API);
			}
			return false;
		}

		/*
			Function: addModerator
				Adds a moderator to this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				user - The ID of the user or the person's username
		*/

		function addModerator($user) {
			$params = array("forum" => $this->ID);
			if (is_numeric($user)) {
				$params["user"] = $user;
			} else {
				$params["user:username"] = $user;
			}
			$response = $this->API->call("forums/addModerator.json",$params,"POST");
			if ($response !== false) {
				$this->API->cacheBust("moderators".$this->ID);
				return true;
			}
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

		function addToBlacklist($type,$value,$retroactive = false,$notes = "") {
			$response = $this->API->call("blacklists/add.json",array("forum" => $this->ID,$type => $value,"retroactive" => $retroactive,"notes" => $notes),"POST");
			if ($response !== false) {
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

		function addToWhitelist($type,$value,$notes = "") {
			$params = array("forum" => $this->ID,"notes" => $notes);
			if ($type == "email") {
				$params["email"] = $value;
			} elseif ($type == "user_id") {
				$params["user"] = $value;
			} elseif ($type == "username") {
				$params["user:username"] = $value;
			}
			$response = $this->API->call("whitelists/add.json",$params,"POST");
			if ($response !== false) {
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
				A BigTreeDisqusResultSet of BigTreeDisqusBlacklistEntry objects
		*/

		function getBlacklist($limit = 25,$order = "asc",$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("blacklists/list.json",$params);
			if ($response !== false) {
				$this->API->cachePush("blacklisted".$this->ID);
				$results = array();
				foreach ($response->Results as $item) {
					$this->API->cachePush("blacklist".$item->id);
					$results[] = new BigTreeDisqusBlacklistEntry($item,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getBlacklist",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getCategories
				Returns categories for this forum.

			Parameters:
				limit - Number of categories to return per page (defaults to 25, max 100)
				order - Sort order (asc or desc, default asc)
				params - Additional parameters to send to forums/listCategories API call

			Returns:
				A BigTreeDisqusResultSet of BigTreeDisqusCategory objects.
		*/

		function getCategories($limit = 25,$order = "asc",$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("forums/listCategories.json",$params);
			if ($response !== false) {
				$this->API->cachePush("categories".$this->ID);
				$results = array();
				foreach ($response->Results as $category) {
					$this->API->cachePush("category".$category->id);
					$results[] = new BigTreeDisqusCategory($category,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getCategories",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getFounder
				Returns information about this forum's founder.

			Returns:
				A BigTreeDisqusUser object.
		*/

		function getFounder() {
			return $this->API->getUser($this->FounderID);
		}

		/*
			Function: getModerators
				Returns an array of moderators for this forum.

			Returns:
				An array of BigTreeDisqusUser objects.
		*/

		function getModerators() {
			$response = $this->API->call("forums/listModerators.json",array("forum" => $this->ID));
			if ($response !== false) {
				$this->API->cachePush("moderators".$this->ID);
				$results = array();
				foreach ($response as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return $results;
			}
			return false;
		}

		/*
			Function: getMostActiveUsers
				Returns a result set of most active users on this forum.

			Parameters:
				limit - Number of users to return per page (defaults to 25, max 100)
				params - Additional parameters to send to forums/listMostActiveUsers API call

			Returns:
				A BigTreeDisqusResultSet of BigTreeDisqusUser objects.
		*/

		function getMostActiveUsers($limit = 25,$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listMostActiveUsers.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getMostActiveUsers",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getMostLikedUsers
				Returns a result set of the most liked users on this forum.

			Parameters:
				limit - Number of users to return per page (defaults to 25, max 100)
				params - Additional parameters to send to forums/listMostActiveUsers API call

			Returns:
				A BigTreeDisqusResultSet of BigTreeDisqusUser objects.
		*/

		function getMostLikedUsers($limit = 25,$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listMostLikedUsers.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getMostLikedUsers",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
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
				A BigTreeDisqusResultSet of BigTreeDisqusPost objects.
		*/

		function getPosts($limit = 25,$order = "desc",$include = array("approved"),$since = false,$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["include"] = $include;
			if ($since) {
				$params["since"] = $since;
			}
			$response = $this->API->call("forums/listPosts.json",$params);
			if ($response !== false) {
				$this->API->cachePush("forumposts".$this->ID);
				$results = array();
				foreach ($response->Results as $post) {
					$this->API->cachePush("post".$post->id);
					$results[] = new BigTreeDisqusPost($post,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getPosts",array($limit,$order,$include,$since,$params),$response->Cursor,$results);
			}
			return false;
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
				A BigTreeDisqusResultSet of BigTreeDisqusThread objects.
		*/

		function getThreads($limit = 25,$order = "desc",$since = false,$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			if ($since) {
				$params["since"] = $since;
			}
			$response = $this->API->call("forums/listThreads.json",$params);
			if ($response !== false) {
				$this->API->cachePush("threads".$this->ID);
				$results = array();
				foreach ($response->Results as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new BigTreeDisqusThread($thread,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getThreads",array($limit,$order,$since,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getTrendingThreads
				Returns an array of trending threads in this forum.

			Parameters:
				limit - Number of threads to return (max 10, default 10)

			Returns:
				An array of BigTreeDisqusPost objects.
		*/

		function getTrendingThreads($limit = 10) {
			$response = $this->API->call("trends/listThreads.json",array("forum" => $this->ID,"limit" => $limit));
			if ($response !== false) {
				$results = array();
				foreach ($response as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new BigTreeDisqusThread($thread,$this->API);
				}
				return $results;
			}
			return false;
		}

		/*
			Function: getUsers
				Returns a result set of users of this forum.

			Parameters:
				limit - Number of users to return (max 100, default 25)
				params - Additional parameters to send to the forums/listUsers API call

			Returns:
				A BigTreeDisqusResultSet of BigTreeDisqusUser objects.
		*/

		function getUsers($limit = 25,$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$response = $this->API->call("forums/listUsers.json",$params);
			if ($response !== false) {
				$this->API->cachePush("users".$this->ID);
				$results = array();
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getUsers",array($limit,$order,$since,$params),$response->Cursor,$results);
			}
			return false;
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
				A BigTreeDisqusResultSet of BigTreeDisqusWhitelistEntry objects
		*/

		function getWhitelist($limit = 25,$order = "asc",$params = array()) {
			$params["forum"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("whitelists/list.json",$params);
			if ($response !== false) {
				$this->API->cachePush("whitelisted".$this->ID);
				$results = array();
				foreach ($response->Results as $item) {
					$this->API->cachePush("whitelist".$item->id);
					$results[] = new BigTreeDisqusWhitelistEntry($item,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getWhitelist",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: removeModerator
				Removes a moderator to this forum.
				Authenticated user must be a moderator of this forum.

			Parameters:
				user - The ID of the user or the person's username
		*/

		function removeModerator($user) {
			$params = array("forum" => $this->ID);
			if (is_numeric($user)) {
				$params["user"] = $user;
			} else {
				$params["user:username"] = $user;
			}
			$response = $this->API->call("forums/removeModerator.json",$params,"POST");
			if ($response !== false) {
				$this->API->cacheBust("moderators".$this->ID);
				return true;
			}
		}
	}

	/*
		Class: BigTreeDisqusPost
			A Disqus object that contains information about and methods you can perform on a forum post.
	*/

	class BigTreeDisqusPost {
		protected $API;

		function __construct($post,&$api) {
			$this->API = $api;
			isset($post->isApproved) ? $this->Approved = $post->isApproved : false;
			isset($post->author) ? $this->Author = new BigTreeDisqusUser($post->author,$api) : false;
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

	/*
		Class: BigTreeDisqusResultSet
			An object that contains multiple results from a Disqus API query.
	*/

	class BigTreeDisqusResultSet {

		/*
			Constructor:
				Creates a result set of Disqus data.

			Parameters:
				object - An instance of an object that is creating this result set.
				last_call - Method called on the object.
				params - The parameters sent to last call.
				cursor - Disqus cursor data.
				results - Results to store.
		*/

		function __construct(&$object,$last_call,$params,$cursor,$results) {
			$this->Cursor = $cursor;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Object = $object;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Returns the next page in the result set.

			Returns:
				A BigTreeDisqusResultSet with the next page of results or false if there isn't another page.
		*/

		function nextPage() {
			if (!$this->Cursor->Next) {
				return false;
			}
			$params = $this->LastParameters;
			$params[count($params) - 1]["cursor"] = $this->Cursor->Next;
			return call_user_func_array(array($this->Object,$this->LastCall),$params);
		}

		/*
			Function: previousPage
				Returns the previous page in the result set.

			Returns:
				A BigTreeDisqusResultSet with the next page of results or false if there isn't a previous page.
		*/

		function previousPage() {
			if (!$this->Cursor->Previous) {
				return false;
			}
			$params = $this->LastParameters;
			$params[count($params) - 1]["cursor"] = $this->Cursor->Previous;
			return call_user_func_array(array($this->Object,$this->LastCall),$params);
		}
	}

	/*
		Class: BigTreeDisqusThread
			A Disqus object that contains information about and methods you can perform on a forum thread.
	*/

	class BigTreeDisqusThread {
		protected $API;

		function __construct($thread,&$api) {
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
			isset($thread->createdAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($thread->createdAt)) : false;
			isset($thread->title) ? $this->Title = $thread->title : false;
			isset($thread->link) ? $this->URL = $thread->link : false;
			isset($thread->userScore) ? $this->UserScore = $thread->userScore : false;
		}

		function _cacheBust() {
			$this->API->cacheBust("forumthreads".$this->ForumID);
			$this->API->cacheBust("thread".$this->ID);
		}

		/*
			Function: close
				Closes this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/

		function close() {
			$response = $this->API->call("threads/close.json",array("thread" => $this->ID),"POST");
			if ($response !== false) {
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
				A BigTreeDisqusResultSet of BigTreeDisqusPost objects.
		*/

		function getPosts($limit = 25,$order = "desc",$params = array()) {
			$params["thread"] = $this->ID;
			$params["limit"] = $limit;
			$params["order"] = $order;
			$response = $this->API->call("threads/listPosts.json",$params);
			if ($response !== false) {
				$this->API->cachePush("threadposts".$this->ID);
				$results = array();
				foreach ($response->Results as $post) {
					$this->API->cachePush("post".$post->id);
					$results[] = new BigTreeDisqusPost($post,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getPosts",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: open
				Opens this thread.
				Authenticated user must be a moderator of this thread's forum.
		*/

		function open() {
			$response = $this->API->call("threads/open.json",array("thread" => $this->ID),"POST");
			if ($response !== false) {
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

		function remove() {
			$response = $this->API->call("threads/remove.json",array("thread" => $this->ID),"POST");
			if ($response !== false) {
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

		function restore() {
			$response = $this->API->call("threads/restore.json",array("thread" => $this->ID),"POST");
			if ($response !== false) {
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

		function subscribe($email = false) {
			$params = array("thread" => $this->ID);
			if ($email) {
				$params["email"] = $email;
			}
			$response = $this->API->call("threads/subscribe.json",$params,"POST");
			if ($response !== false) {
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

		function unsubscribe($email = false) {
			$params = array("thread" => $this->ID);
			if ($email) {
				$params["email"] = $email;
			}
			$response = $this->API->call("threads/unsubscribe.json",$params,"POST");
			if ($response !== false) {
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

		function vote($vote = 0) {
			$response = $this->API->call("threads/vote.json",array("thread" => $this->ID,"vote" => $vote),"POST");
			if ($response !== false) {
				$this->_cacheBust();
				return true;
			}
			return false;
		}
	}

	/*
		Class: BigTreeDisqusUser
			A Disqus object that contains information about and methods you can perform on a user.
	*/

	class BigTreeDisqusUser {
		protected $API;

		function __construct($user,&$api) {
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
			isset($user->joinedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($user->joinedAt)) : false;
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

		function getActiveForums($limit = 25,$params = array()) {
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listActiveForums.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $forum) {
					$this->API->cachePush("forum".$forum->id);
					$results[] = new BigTreeDisqusForum($forum,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getActiveForums",array($limit,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getActiveThreads
				Returns a result set of threads this user is active in.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listActiveForums API call
		*/

		function getActiveThreads($limit = 25,$params = array()) {
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listActiveThreads.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $thread) {
					$this->API->cachePush("thread".$thread->id);
					$results[] = new BigTreeDisqusThread($thread,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getActiveThreads",array($limit,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getFollowers
				Returns a result set of users that follow this user.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listFollowers API call
		*/

		function getFollowers($limit = 25,$params = array()) {
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listFollowers.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getFollowers",array($limit,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getFollowing
				Returns a result set of users that this user follows.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				params - Additional parameters to send to users/listFollowing API call
		*/

		function getFollowing($limit = 25,$params = array()) {
			$params["limit"] = $limit;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listFollowing.json",$params);
			if ($response !== false) {
				$results = array();
				foreach ($response->Results as $user) {
					$this->API->cachePush("user".$user->id);
					$results[] = new BigTreeDisqusUser($user,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getFollowing",array($limit,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: getPosts
				Returns a result set of posts by this user.

			Parameters:
				limit - Number of results per page (default is 25, max is 100)
				order - Sort order (asc or desc, defaults to desc)
				params - Additional parameters to send to users/listPosts API call
		*/

		function getPosts($limit = 25,$order = "desc",$params = array()) {
			$params["limit"] = $limit;
			$params["order"] = $order;
			$params["user"] = $this->ID;
			$response = $this->API->call("users/listPosts.json",$params);
			if ($response !== false) {
				$this->API->cachePush("userposts".$this->ID);
				$results = array();
				foreach ($response->Results as $post) {
					$this->API->cachePush("post".$post->id);
					$results[] = new BigTreeDisqusPost($post,$this->API);
				}
				return new BigTreeDisqusResultSet($this,"getPosts",array($limit,$order,$params),$response->Cursor,$results);
			}
			return false;
		}

		/*
			Function: follow
				Causes the authenticated user to follow this user.

			Returns:
				true if successful.
		*/

		function follow() {
			$response = $this->API->call("users/follow.json",array("target" => $this->ID),"POST");
			if ($response !== false) {
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

		function unfollow() {
			$response = $this->API->call("users/unfollow.json",array("target" => $this->ID),"POST");
			if ($response !== false) {
				$this->API->cacheBust("user".$this->ID);
				return true;
			}
			return false;
		}
	}

	/*
		Class: BigTreeDisqusWhitelistEntry
			A Disqus object that contains information about and methods you can perform on a whitelist entry.
	*/

	class BigTreeDisqusWhitelistEntry {
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
?>