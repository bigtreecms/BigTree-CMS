<?
	$api = new BigTreeFacebookAPI(false);
	$name = "Facebook";
	$route = "facebook";
	$key_name = "App ID";
	$secret_name = "App Secret";
	$show_test_environment = false;
	$scope_default = "user_about_me,user_actions.books,user_actions.fitness,user_actions.music,user_actions.news,user_actions.video,user_birthday,user_education_history,user_events,user_games_activity,user_hometown,user_likes,user_location,user_managed_groups,user_photos,user_posts,user_relationships,user_relationship_details,user_religion_politics,user_status,user_tagged_places,user_videos,user_website,user_work_history,manage_pages,publish_pages,publish_actions";
	$scope_help = " <small>(see <a href=\"https://developers.facebook.com/docs/facebook-login/permissions/v2.6\" target=\"_blank\">Permissions</a>)</small>";
	$instructions = array(
		'<a href="https://developers.facebook.com/apps/" target="_blank">Add a new Facebook app</a>.',
		'In the left navigation bar of your project dashboard click the Add Product link.',
		'Add the Facebook Login product.',
		'Under Facebook Login, find the Client OAuth Settings section and add '.DEVELOPER_ROOT.'services/facebook/return/ as a valid OAuth redirect URI.',
		'Click "Save Changes".',
		'Go back to the Settings page and enter the App ID and App Secret from that page below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Facebook account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->getUser();
		$api->Settings["user_name"] = $user->Name;
		$api->Settings["user_image"] = $user->getPicture();
		$api->Settings["user_id"] = $user->ID;
	}
?>