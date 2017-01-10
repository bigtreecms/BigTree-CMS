<?php
	/*
		Class: BigTree\GooglePlus\API
			Google+ API class that implements people and activity related calls.
	*/
	
	namespace BigTree\GooglePlus;
	
	use BigTree\OAuth;
	use BigTree\GoogleResultSet;
	
	class API extends OAuth {
		
		public $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		public $EndpointURL = "https://www.googleapis.com/plus/v1/";
		public $OAuthVersion = "1.0";
		public $RequestType = "custom";
		public $Scope = "https://www.googleapis.com/auth/plus.login";
		public $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
		/*
			Constructor:
				Sets up the Google+ API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		function __construct(bool $cache = true) {
			parent::__construct("bigtree-internal-googleplus-api", "Google+ API", "org.bigtreecms.api.googleplus", $cache);
			
			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/googleplus/return/";
			
			// Just send the request with the secret.
			$this->RequestParameters = [];
			$this->RequestParameters["access_token"] = &$this->Settings["token"];
			$this->RequestParameters["api_key"] = &$this->Settings["key"];
			$this->RequestParameters["api_secret"] = &$this->Settings["secret"];
		}
		
		/*
			Function: getActivity
				Returns information about a given activity ID.

			Parameters:
				id - The ID of the activity.

			Returns:
				A BigTree\GooglePlus\Activity object.
		*/
		
		function getActivity(string $id): ?Activity {
			$response = $this->call("activities/$id");
			
			if (!$response->id) {
				return null;
			}
			
			return new Activity($response, $this);
		}
		
		/*
			Function: getActivities
				Returns a list of public activities made by the given user ID.
				Returns the authenticated user's activities if no ID is passed in.

			Parameters:
				user - The ID of the person to return activities for. You may pass "me" to use the authenticated user (default)
				count - The number of results to return, (defaults to 100, max 100)
				params - Additional parameters to pass to the people/{userId}/activities API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\Activity objects.
		*/
		
		function getActivities(string $user = "me", int $count = 100, array $params = []): ?GoogleResultSet {
			$params["maxResults"] = $count;
			$response = $this->call("people/$user/activities/public", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $activity) {
				$results[] = new Activity($activity, $this);
			}
			
			return new GoogleResultSet($this, "getActivities", [$user, $count, $params], $response, $results);
		}
		
		/*
			Function: getCircledPeople
				Returns a list of people the given user has in one or more circles.
				Returns circled people for the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return circled people for. You may pass "me" to use the authenticated user (default)
				count - The number of results to return, (defaults to 100, max 100)
				order - The sort order for the results (options are "best" and "alphabetical", defaults to "best")
				params - Additional parameters to pass to the people/{userId}/people API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\People objects.
		*/
		
		function getCircledPeople(string $user = "me", int $count = 100, string $order = "best",
								  array $params = []): ?GoogleResultSet {
			$params["orderBy"] = $order;
			$params["maxResults"] = $count;
			$response = $this->call("people/$user/people/visible", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $person) {
				$results[] = new Person($person, $this);
			}
			
			return new GoogleResultSet($this, "getCircledPeople", [$user, $count, $order, $params], $response, $results);
		}
		
		/*
			Function: getComment
				Returns a comment with the given ID.

			Parameters:
				id - The comment ID.

			Returns:
				A BigTree\GooglePlus\Comment object.
		*/
		
		function getComment(string $id): ?Comment {
			$response = $this->call("comments/$id");
			
			if (!isset($response->id)) {
				return null;
			}
			
			return new Comment($response, $this);
		}
		
		/*
			Function: getComments
				Returns comments for a given activity ID.

			Parameters:
				activity - The activity ID to pull comments for.
				count - The number of comments to return (defaults to 500, max 500)
				order - The sort order for the results (options are "ascending" and "descending", defaults to "ascending" or oldest first)
				params - Additional parameters to pass to the activities/{activityId}/comments API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\Comment objects.
		*/
		
		function getComments(string $activity, int $count = 500, string $order = "ascending", array $params = []): ?GoogleResultSet {
			$params["orderBy"] = $order;
			$params["maxResults"] = $count;
			$response = $this->call("activities/$activity/comments", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $comment) {
				$results[] = new Comment($comment, $this);
			}
			
			return new GoogleResultSet($this, "getComments", [$activity, $count, $order, $params], $response, $results);
		}
		
		/*
			Function: getPerson
				Returns a person for the given user ID.
				Returns the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return.

			Returns:
				A BigTree\GooglePlus\Person object.
		*/
		
		function getPerson(string $user = "me"): ?Person {
			$response = $this->call("people/$user");
			
			if (!$response->id) {
				return null;
			}
			
			return new Person($response, $this);
		}
		
		/*
			Function: searchActivities
				Searches for public activities.

			Parameters:
				query - A string to search for.
				count - Number of results to return (defaults to 10, max 20)
				order - Sort order for results (options are "best" and "recent", defaults to "best")
				params - Additional parameters to pass to the activities API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\Activity objects.
		*/
		
		function searchActivities(string $query, int $count = 10, string $order = "best", array $params = []): ?GoogleResultSet {
			$params["query"] = $query;
			$params["orderBy"] = $order;
			$params["maxResults"] = ($count > 20) ? 20 : $count; // Google+ fails if you pass too high of a count.
			
			$response = $this->call("activities", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $activity) {
				$results[] = new Activity($activity, $this);
			}
			
			return new GoogleResultSet($this, "searchActivities", [$query, $count, $order, $params], $response, $results);
		}
		
		/*
			Function: searchPeople
				Searches for people.

			Parameters:
				query - A string to search for.
				count - Number of results to return (defaults to 10, max 20)
				params - Additional parameters to pass to the people API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\Person objects.
		*/
		
		function searchPeople(string $query, int $count = 10, array $params = []): ?GoogleResultSet {
			$params["query"] = $query;
			$params["maxResults"] = ($count > 20) ? 20 : $count; // Google+ fails if you pass too high of a count.
			
			$response = $this->call("people", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $person) {
				$results[] = new Person($person, $this);
			}
			
			return new GoogleResultSet($this, "searchPeople", [$query, $count, $params], $response, $results);
		}
		
	}
