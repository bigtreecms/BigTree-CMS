<?
	/*
		Class: BigTreeGooglePlusAPI
			Google+ API class that implements people and activity related calls.
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));
	require_once(BigTree::path("inc/bigtree/apis/_google.result-set.php"));

	class BigTreeGooglePlusAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		var $EndpointURL = "https://www.googleapis.com/plus/v1/";
		var $OAuthVersion = "1.0";
		var $RequestType = "custom";
		var $Scope = "https://www.googleapis.com/auth/plus.login";
		var $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
		/*
			Constructor:
				Sets up the Google+ API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-googleplus-api","Google+ API","org.bigtreecms.api.googleplus",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/googleplus/return/";

			// Just send the request with the secret.
			$this->RequestParameters = array();
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
				A BigTreeGooglePlusActivity object.
		*/

		function getActivity($id) {
			$response = $this->call("activities/$id");
			if (!$response->id) {
				return false;
			}
			return new BigTreeGooglePlusActivity($response,$this);
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
				A BigTreeGoogleResultSet of BigTreeGooglePlusActivity objects.
		*/

		function getActivities($user = "me",$count = 100,$params = array()) {
			$response = $this->call("people/$user/activities/public",array_merge(array(
				"orderBy" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $activity) {
				$results[] = new BigTreeGooglePlusActivity($activity,$this);
			}
			return new BigTreeGoogleResultSet($this,"getActivities",array($user,$count,$order,$params),$response,$results);
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
				A BigTreeGoogleResultSet of BigTreeGooglePlusPeople objects.
		*/

		function getCircledPeople($user = "me",$count = 100,$order = "best",$params = array()) {
			$response = $this->call("people/$user/people/visible",array_merge(array(
				"orderBy" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $person) {
				$results[] = new BigTreeGooglePlusPerson($person,$this);
			}
			return new BigTreeGoogleResultSet($this,"getCircledPeople",array($user,$count,$order,$params),$response,$results);
		}

		/*
			Function: getComment
				Returns a comment with the given ID.

			Parameters:
				id - The comment ID.

			Returns:
				A BigTreeGooglePlusComment object.
		*/

		function getComment($id) {
			$response = $this->call("comments/$id");
			if (!isset($response->id)) {
				return false;
			}
			return new BigTreeGooglePlusComment($response,$this);
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
				A BigTreeGoogleResultSet of BigTreeGooglePlusComment objects.
		*/

		function getComments($activity,$count = 500,$order = "ascending",$params = array()) {
			$response = $this->call("activities/$activity/comments",array_merge(array(
				"orderBy" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $comment) {
				$results[] = new BigTreeGooglePlusComment($comment,$this);
			}
			return new BigTreeGoogleResultSet($this,"getComments",array($activity,$count,$order,$params),$response,$results);
		}

		/*
			Function: getPerson
				Returns a person for the given user ID.
				Returns the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return.

			Returns:
				A BigTreeGooglePlusPerson object.
		*/

		function getPerson($user = "me") {
			$response = $this->call("people/$user");
			if (!$response->id) {
				return false;
			}
			return new BigTreeGooglePlusPerson($response,$this);
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
				A BigTreeGoogleResultSet of BigTreeGooglePlusActivity objects.
		*/

		function searchActivities($query,$count = 10,$order = "best",$params = array()) {
			// Google+ fails if you pass too high of a count.
			if ($count > 20) {
				$count = 20;
			}
			$response = $this->call("activities",array_merge(array(
				"query" => $query,
				"orderBy" => $order,
				"maxResults" => $count
			),$params));
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $activity) {
				$results[] = new BigTreeGooglePlusActivity($activity,$this);
			}
			return new BigTreeGoogleResultSet($this,"searchActivities",array($query,$count,$order,$params),$response,$results);
		}

		/*
			Function: searchPeople
				Searches for people.

			Parameters:
				query - A string to search for.
				count - Number of results to return (defaults to 10, max 20)
				params - Additional parameters to pass to the people API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGooglePlusPerson objects.
		*/

		function searchPeople($query,$count = 10,$params = array()) {
			// Google+ fails if you pass too high of a count.
			if ($count > 20) {
				$count = 20;
			}
			$response = $this->call("people",array_merge(array(
				"query" => $query,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $person) {
				$results[] = new BigTreeGooglePlusPerson($person,$this);
			}
			return new BigTreeGoogleResultSet($this,"searchPeople",array($query,$count,$params),$response,$results);
		}
	}

	/*
		Class: BigTreeGooglePlusActivity
			A Google+ object that contains information about and methods you can perform on an activity.
	*/

	class BigTreeGooglePlusActivity {
		protected $API;

		function __construct($activity,&$api) {
			if (is_array($activity->access->items)) {
				$this->Access = new stdClass;
				isset($activity->access->description) ? $this->Access->Description = $activity->access->description : false;
				$this->Access->Items = array();
				foreach ($activity->access->items as $item) {
					$i = new stdClass;
					isset($item->type) ? $i->Type = $item->type : false;
					isset($item->id) ? $i->ID = $item->id : false;
					$this->Access->Items[] = $i;
				}
			}
			$this->API = $api;
			isset($activity->object->content) ? $this->Content = $activity->object->content : false;
			isset($activity->object->originalContent) ? $this->ContentPlainText = $activity->object->originalContent : false;
			isset($activity->object->url) ? $this->ContentURL = $activity->object->url : false;
			isset($activity->published) ? $this->CreatedAt = date("Y-m-d H:i:s",strtotime($activity->published)) : false;
			isset($activity->crosspostSource) ? $this->CrosspostSource = $activity->crosspostSource : false;
			isset($activity->id) ? $this->ID = $activity->id : false;
			isset($activity->location) ? $this->Location = new BigTreeGooglePlusLocation($activity->location,$api) : false;
			if (is_array($activity->object->attachments)) {
				$this->Media = array();
				foreach ($activity->object->attachments as $item) {
					$m = new stdClass;
					isset($item->content) ? $m->Content = $item->content : false;
					isset($item->embed->type) ? $m->EmbedType = $item->embed->type : false;
					isset($item->embed->url) ? $m->EmbedURL = $item->embed->url : false;
					isset($item->id) ? $m->ID = $item->id : false;
					if (isset($item->fullImage)) {
						isset($item->fullImage->url) ? $m->Image = $item->fullImage->url : false;
						isset($item->fullImage->type) ? $m->ImageType = $item->fullImage->type : false;
						isset($item->fullImage->height) ? $m->ImageHeight = $item->fullImage->height : false;
						isset($item->fullImage->width) ? $m->ImageWidth = $item->fullImage->width : false;
					}
					if (isset($item->image)) {
						isset($item->image->url) ? $m->Thumbnail = $item->image->url : false;
						isset($item->image->type) ? $m->ThumbnailType = $item->image->type : false;
						isset($item->image->height) ? $m->ThumbnailHeight = $item->image->height : false;
						isset($item->image->width) ? $m->ThumbnailWidth = $item->image->width : false;
					}
					isset($item->displayName) ? $m->Title = $item->displayName : false;
					isset($item->objectType) ? $m->Type = $item->objectType : false;
					isset($item->url) ? $m->URL = $item->url : false;
					$this->Media[] = $m;
				}
			}
			isset($activity->annotation) ? $this->Note = $activity->annotation : false;
			isset($activity->plusoners->totalItems) ? $this->PlusOneCount = $activity->object->plusoners->totalItems : false;
			isset($activity->replies->totalItems) ? $this->ReplyCount = $activity->object->replies->totalItems : false;
			if ($activity->verb == "share") {
				$this->Reshare = true;
				isset($activity->object->id) ? $this->ResharedID = $activity->object->id : false;
				isset($activity->object->actor) ? $this->ResharedUser = new BigTreeGooglePlusPerson($activity->object->actor,$api) : false;
			}
			isset($activity->resharers->totalItems) ? $this->ReshareCount = $activity->object->resharers->totalItems : false;
			isset($activity->provider->title) ? $this->Service = $activity->provider->title : false; 
			isset($activity->title) ? $this->Title = $activity->title : false;
			isset($activity->object->objectType) ? $this->Type = $activity->object->objectType : false;
			isset($activity->updated) ? $this->UpdatedAt = date("Y-m-d H:i:s",strtotime($activity->updated)) : false;
			isset($activity->url) ? $this->URL = $activity->url : false;
			isset($activity->actor) ? $this->User = new BigTreeGooglePlusPerson($activity->actor,$api) : false;
		}

		/*
			Function: getComments
				Returns comments for this activity.

			Parameters:
				count - The number of comments to return (defaults to 500, max 500)
				order - The sort order for the results (options are "ascending" and "descending", defaults to "ascending" or oldest first)
				params - Additional parameters to pass to the activities/{activityId}/comments API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGooglePlusComment objects.
		*/

		function getComments($count = 500,$order = "ascending",$params = array()) {
			return $this->API->getComments($this->ID,$count,$order,$params);
		}

	}

	/*
		Class: BigTreeGooglePlusComment
			A Google+ object that contains information about and methods you can perform on a comment.
	*/

	class BigTreeGooglePlusComment {
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
			isset($comment->actor) ? $this->User = new BigTreeGooglePlusPerson($comment->actor,$api) : false;
		}
	}

	/*
		Class: BigTreeGooglePlusLocation
			A Google+ object that contains information about and methods you can perform on a location.
	*/

	class BigTreeGooglePlusLocation {
		protected $API;

		function __construct($location,&$api) {
			$this->API = $api;
			isset($location->address->formatted) ? $this->Address = $location->address->formatted : false;
			isset($location->position->latitude) ? $this->Latitude = $location->position->latitude : false;
			isset($location->position->longitude) ? $this->Longitude = $location->position->longitude : false;
			isset($location->displayName) ? $this->Name = $location->displayName : false;
		}
	}

	/*
		Class: BigTreeGooglePlusPerson
			A Google+ object that contains information about and methods you can perform on a person.
	*/

	class BigTreeGooglePlusPerson {
		protected $API;

		function __construct($person,&$api) {
			$this->API = $api;
			isset($person->ageRange->min) ? $this->AgeRangeMin = $person->ageRange->min : false;
			isset($person->ageRange->max) ? $this->AgeRangeMax = $person->ageRange->max : false;
			isset($person->birthday) ? $this->Birthday = $person->birthday : false;
			isset($person->braggingRights) ? $this->BraggingRights = $person->braggingRights : false;
			isset($person->circledByCount) ? $this->CircledByCount = $person->circledByCount : false;
			if (isset($person->cover)) {
				$this->Cover = new stdClass;
				isset($person->cover->coverPhoto->height) ? $this->Cover->Height = $person->cover->coverPhoto->height : false;
				isset($person->cover->layout) ? $this->Cover->Layout = $person->cover->layout : false;
				isset($person->cover->coverInfo->leftImageOffset) ? $this->Cover->OffsetLeft = $person->cover->coverInfo->leftImageOffset : false;
				isset($person->cover->coverInfo->topImageOffset) ? $this->Cover->OffsetTop = $person->cover->coverInfo->topImageOffset : false;
				isset($person->cover->coverPhoto->url) ? $this->Cover->Photo = $person->cover->coverPhoto->url : false;
				isset($person->cover->coverPhoto->width) ? $this->Cover->Width = $person->cover->coverPhoto->width : false;
			}
			isset($person->currentLocation) ? $this->CurrentLocation = $person->currentLocation : false;
			isset($person->aboutMe) ? $this->Description = $person->aboutMe : false;
			isset($person->displayName) ? $this->DisplayName = $person->displayName : false;
			if (is_array($person->organizations)) {
				$this->Education = array();
				$this->Employment = array();
				foreach ($person->organizations as $org) {
					$o = new stdClass;
					isset($org->name) ? $o->Name = $org->name : false;
					isset($org->title) ? $o->Title = $org->title : false;
					isset($org->startDate) ? $o->StartDate = date("Y-m-d",strtotime($org->startDate)) : false;
					isset($org->endDate) ? $o->EndDate = date("Y-m-d",strtotime($org->endDate)) : false; 
					isset($org->primary) ? $o->Primary = $org->primary : false;
					if ($org->type == "school") {
						$this->Education[] = $o;
					} elseif ($org->type == "work") {
						$this->Employment[] = $o;
					}
				}
			}
			if (is_array($person->emails)) {
				$this->Emails = array();
				foreach ($person->emails as $e) {
					$email = new stdClass;
					isset($e->value) ? $email->Address = $e->value : false;
					isset($e->primary) ? $email->Primary = $e->primary : false;
					isset($e->type) ? $email->Type = $e->type : false;
					$this->Emails[] = $email;
				}
			}
			isset($person->gender) ? $this->Gender = $person->gender : false;
			isset($person->id) ? $this->ID = $person->id : false;
			isset($person->hasApp) ? $this->HasApp = $person->hasApp : false;
			isset($person->image->url) ? $this->Image = $person->image->url : false;
			if (is_array($person->urls)) {
				$this->Links = array();
				foreach ($person->urls as $url) {
					$link = new stdClass;
					isset($url->label) ? $link->Name = $url->label : false;
					isset($url->primary) ? $link->Primary = $url->primary : false;
					isset($url->type) ? $link->Type = $url->type : false;
					isset($url->value) ? $link->URL = $url->value : false;
					$this->Links[] = $link;
				}
			}
			isset($person->isPlusUser) ? $this->IsPlusUser = $person->isPlusUser : false;
			isset($person->language) ? $this->Language = $person->language : false;
			isset($person->name) ? $this->Name = new stdClass : false;
			isset($person->name->formatted) ? $this->Name->Formatted = $person->name->formatted : false;
			isset($person->name->honorificPrefix) ? $this->Name->Prefix = $person->name->honorificPrefix : false;
			isset($person->name->givenName) ? $this->Name->First = $person->name->givenName : false;
			isset($person->name->middleName) ? $this->Name->Middle = $person->name->middleName : false;
			isset($person->name->familyName) ? $this->Name->Last = $person->name->familyName : false;
			isset($person->name->honorificSuffix) ? $this->Name->Suffix = $person->name->honorificSuffix : false;
			isset($person->nickname) ? $this->Name->Preferred = $person->nickname : false;
			if (is_array($person->placesLived)) {
				$this->Places = array();
				foreach ($person->placesLived as $pl) {
					$loc = new stdClass;
					isset($pl->value) ? $loc->Location = $pl->value : false;
					isset($pl->primary) ? $loc->Primary = $pl->primary : false;
					$this->Places[] = $loc;
				}
			}
			isset($person->plusOneCount) ? $this->PlusOneCount = $person->plusOneCount : false;
			isset($person->relationshipStatus) ? $this->RelationshipStatus = $person->relationshipStatus : false;
			isset($person->tagline) ? $this->Tagline = $person->tagline : false;
			isset($person->objectType) ? $this->Type = $person->objectType : false;
			isset($person->url) ? $this->URL = $person->url : false;
			isset($person->verified) ? $this->Verified = $person->verified : false;
		}

		/*
			Function: getActivities
				Returns a list of public activities made by this person.

			Parameters:
				count - The number of results to return, (defaults to 100, max 100)
				params - Additional parameters to pass to the people/{userId}/activities API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGooglePlusActivity objects.
		*/

		function getActivities($count = 100,$params = array()) {
			return $this->API->getActivities($this->ID,$count,$params);
		}

		/*
			Function: getCircledPeople
				Returns a list of people this user has in one or more circles.

			Parameters:
				count - The number of results to return, (defaults to 100, max 100)
				order - The sort order for the results (options are "best" and "alphabetical", defaults to "best")
				params - Additional parameters to pass to the people/{userId}/people API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeGooglePlusPeople objects.
		*/

		function getCircledPeople($count = 100,$order = "best",$params = array()) {
			return $this->API->getCircledPeople($this->ID,$count,$order,$params);
		}
	}
?>