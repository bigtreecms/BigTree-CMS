<?php
	/*
		Class: BigTreeFacebookAPI
			Facebook API class that implements some API calls.
	*/
	
	require_once SERVER_ROOT."core/inc/bigtree/apis/_oauth.base.php";
	class BigTreeFacebookAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://www.facebook.com/dialog/oauth";
		var $EndpointURL = "https://graph.facebook.com/v2.4/";
		var $OAuthVersion = "2.0";
		var $RequestType = "header";
		var $Scope = "";
		var $TokenURL = "https://graph.facebook.com/v2.4/oauth/access_token";
		
		/*
			Constructor:
				Sets up the Facebook API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-facebook-api","Facebook API","org.bigtreecms.api.facebook",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/facebook/return/";

			// Set access scope
			$this->Scope = $this->Settings["scope"];
		}

		/*
			Function: getUser
				Returns a user for the given user ID.
				Returns the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return.

			Returns:
				A BigTreeFacebookPerson object.
		*/

		function getUser($user = "me") {
			$response = $this->call($user);
			if (!$response->id) {
				return false;
			}
			return new BigTreeFacebookUser($response,$this);
		}
	}

	/*
		Class: BigTreeFacebookUser
			Facebook API class for a user.
	*/

	class BigTreeFacebookUser {
		protected $API;

		function __construct($user,&$api) {
			$this->API = $api;
			$this->ID = $user->id;
			$this->Name = $user->name;
			$this->updateDetails($user);
		}

		/*
			Function: updateDetails
				Updates the user object's details with fresh information from the API.
				Most useful when a stub of a user is sent (i.e. another user's SignificantOther)

			Parameters:
				user - A pre-composed data set (optional, defaults to pulling from API)
		*/

		function updateDetails($user = false) {
			if (!$user) {
				$user = $this->API->call($this->ID);
			}

			isset($user->bio) ? $this->Biography = $user->bio : false;
			isset($user->birthday) ? $this->Birthday = $user->birthday : false;
			if (is_array($user->education)) {
				foreach ($user->education as $school) {
					$this->Education[] = new BigTreeFacebookSchool($school["school"],$school["type"],$api);
				}
			}
			isset($user->email) ? $this->Email = $user->email : false;
			isset($user->first_name) ? $this->FirstName = $user->first_name : false;
			isset($user->gender) ? $this->Gender = $user->gender : false;
			isset($user->hometown) ? $this->Hometown = new BigTreeFacebookLocation($user->hometown,$api) : false;
			isset($user->last_name) ? $this->LastName = $user->last_name : false;
			isset($user->updated_time) ? $this->LastUpdate = date("Y-m-d H:i:s",strtotime($user->updated_time)) : false;
			isset($user->locale) ? $this->Locale = $user->locale : false;
			isset($user->location) ? $this->Location = new BigTreeFacebookLocation($user->location,$api) : false;
			isset($user->political) ? $this->Political = $user->political : false;
			isset($user->relationship_status) ? $this->RelationshipStatus = $user->relationship_status : false;
			isset($user->religion) ? $this->Religion = $user->religion : false;
			isset($user->significant_other) ? $this->SignificantOther = new BigTreeFacebookUser($user->significant_other,$api) : false;
			isset($user->timezone) ? $this->Timezone = $user->timezone : false;
			isset($user->link) ? $this->URL = $user->link : false;
			isset($user->verified) ? $this->Verified = $user->verified : false;
			if (isset($user->work)) {
				$this->Work = new stdClass;
				isset($user->work["employer"]) ? $this->Work->Employer = new BigTreeFacebookEmployer($user->work["employer"],$api) : false;
				isset($user->work["location"]) ? $this->Work->Location = new BigTreeFacebookLocation($user->work["location"],$api) : false;
				isset($user->work["position"]) ? $this->Work->JobTitle = new BigTreeFacebookJobTitle($user->work["position"],$api) : false;
			}
		}

		/*
			Function: getPicture
				Returns the URL for the user's photo and updates the class instance with the information.
				Facebook returns a photo of the approximate size requested.
			
			Parameters:
				width - The approximate width of the picture to return (defaults to 1000)
				height - The approximate height of the picture to return (defaults to 1000)

			Returns:
				A URL or false on failure
		*/

		function getPicture($width = 1000,$height = 1000) {
			if ($this->Picture) {
				return $this->Picture;
			}

			$response = $this->API->call($this->ID."/picture?redirect=false&width=".intval($width)."&height=".intval($height));
			if (isset($response->data->url)) {
				$this->Picture = $response->data->url;
				return $this->Picture;
			}

			return false;
		}
	}

	/*
		Class: BigTreeFacebookSchool
			Facebook API class for a school.
	*/

	class BigTreeFacebookSchool {
		protected $API;

		function __construct($school,$type,&$api) {
			$this->API = $api;

			$this->ID = $school->id;
			$this->Name = $school->name;
			$this->Type = $type;
		}
	}

	/*
		Class: BigTreeFacebookLocation
			Facebook API class for a location.
	*/

	class BigTreeFacebookLocation {
		protected $API;

		function __construct($location,&$api) {
			$this->API = $api;

			$this->ID = $location->id;
			$this->Name = $location->name;
		}
	}

	/*
		Class: BigTreeFacebookJobTitle
			Facebook API class for a job title.
	*/

	class BigTreeFacebookJobTitle {
		protected $API;

		function __construct($job,&$api) {
			$this->API = $api;

			$this->ID = $job->id;
			$this->Name = $job->name;
		}
	}
?>