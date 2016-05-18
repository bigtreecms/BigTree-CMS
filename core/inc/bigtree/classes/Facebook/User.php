<?php
	/*
		Class: BigTree\Facebook\User
			Facebook API class for a user.
	*/

	namespace BigTree\Facebook;

	use stdClass;

	class User {

		/** @var \BigTree\Facebook\API */
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
				$this->Education = array();
				foreach ($user->education as $school) {
					$this->Education[] = new School($school["school"],$school["type"],$this->API);
				}
			}
			isset($user->email) ? $this->Email = $user->email : false;
			isset($user->first_name) ? $this->FirstName = $user->first_name : false;
			isset($user->gender) ? $this->Gender = $user->gender : false;
			isset($user->hometown) ? $this->Hometown = new Location($user->hometown,$this->API) : false;
			isset($user->last_name) ? $this->LastName = $user->last_name : false;
			isset($user->updated_time) ? $this->LastUpdate = date("Y-m-d H:i:s",strtotime($user->updated_time)) : false;
			isset($user->locale) ? $this->Locale = $user->locale : false;
			isset($user->location) ? $this->Location = new Location($user->location,$this->API) : false;
			isset($user->political) ? $this->Political = $user->political : false;
			isset($user->relationship_status) ? $this->RelationshipStatus = $user->relationship_status : false;
			isset($user->religion) ? $this->Religion = $user->religion : false;
			isset($user->significant_other) ? $this->SignificantOther = new User($user->significant_other,$this->API) : false;
			isset($user->timezone) ? $this->Timezone = $user->timezone : false;
			isset($user->link) ? $this->URL = $user->link : false;
			isset($user->verified) ? $this->Verified = $user->verified : false;
			if (isset($user->work)) {
				$this->Work = new stdClass;
				isset($user->work["employer"]) ? $this->Work->Employer = $user->work["employer"] : false;
				isset($user->work["location"]) ? $this->Work->Location = new Location($user->work["location"],$this->API) : false;
				isset($user->work["position"]) ? $this->Work->JobTitle = new JobTitle($user->work["position"],$this->API) : false;
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
	