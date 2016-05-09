<?php
	/*
		Class: BigTree\Disqus\API
			The main Disqus API class used to retrieve lower level Disqus objects.
	*/

	namespace BigTree\Disqus;
	
	use BigTree\OAuth;
	use stdClass;

	class API extends OAuth {

		public $AuthorizeURL = "https://disqus.com/api/oauth/2.0/authorize/";
		public $EndpointURL = "https://disqus.com/api/3.0/";
		public $OAuthVersion = "1.0";
		public $RequestType = "custom";
		public $Scope = "read,write,admin";
		public $TokenURL = "https://disqus.com/api/oauth/2.0/access_token/";
		
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

		function callUncached($endpoint = "",$params = array(),$method = "GET",$headers = array()) {
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
				A BigTree\Disqus\Forum object.
				Returns false if the shortname is already taken.
		*/

		function createForum($shortname,$name,$url) {
			$response = $this->call("forums/create.json",array("website" => $url,"name" => $name,"short_name" => $shortname),"POST");
			if ($response !== false) {
				return new Forum($response,$this);
			}
			return false;
		}

		/*
			Function: getCategory
				Returns a BigTree\Disqus\Category object for the given category id.

			Parameters:
				id - The category id

			Returns:
				A BigTree\Disqus\Category object if successful.
		*/

		function getCategory($id) {
			$response = $this->call("categories/details.json",array("category" => $id));
			if ($response !== false) {
				$this->cachePush("category".$response->id);
				return new Category($response,$this);
			}
			return false;
		}

		/*
			Function: getForum
				Returns a BigTree\Disqus\Forum object for the given forum shortname.

			Parameters:
				shortname - The forum shortname

			Returns:
				A BigTree\Disqus\Forum object if successful.
		*/

		function getForum($shortname) {
			$response = $this->call("forums/details.json",array("forum" => $shortname));
			if ($response !== false) {
				$this->cachePush("forum".$response->id);
				return new Forum($response,$this);
			}
			return false;
		}

		/*
			Function: getPost
				Returns a BigTree\Disqus\Post object for the given post ID.

			Parameters:
				post - The post ID

			Returns:
				A BigTree\Disqus\Post object if successful.
		*/

		function getPost($id) {
			$response = $this->call("posts/details.json",array("post" => $id));
			if ($response !== false) {
				$this->cachePush("post".$response->id);
				return new Post($response,$this);
			}
			return false;
		}

		/*
			Function: getThread
				Returns a BigTree\Disqus\Thread object for the given thread ID.

			Parameters:
				thread - The thread ID, identifier, or link
				forum - If looking up by link, the shortname for the forum is required.

			Returns:
				A BigTree\Disqus\Thread object if successful.
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
				return new Thread($response,$this);
			}
			return false;
		}

		/*
			Function: getUser
				Returns a BigTree\Disqus\User object for the given user.
				If no user is passed in, the authenticated user's information is returned.

			Parameters:
				user - The ID of the user or the person's username (leave blank to use the authenticated user)

			Returns:
				A BigTree\Disqus\User object if successful.
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
				return new User($response,$this);
			}
			return false;
		}

	}

