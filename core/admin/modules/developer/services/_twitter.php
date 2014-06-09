<?
	$api = new BigTreeTwitterAPI;
	$name = "Twitter";
	$route = "twitter";
	$key_name = "API Key";
	$secret_name = "API Secret";
	$show_test_environment = false;
	$instructions = array(
		'Create a <a href="https://apps.twitter.com/" target="_blank">Twitter Application</a> at the Twitter Developers portal.',
		'Set the application\'s Website to '.DOMAIN.' and it\'s Callback URL to '.DEVELOPER_ROOT.'services/twitter/return/',
		'Enter the application\'s "API Key" and "API Secret" below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Twitter account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->callUncached("account/verify_credentials.json");
		$api->Settings["user_id"] = $user->id;
		$api->Settings["user_name"] = $user->screen_name;
		$api->Settings["user_image"] = $user->profile_image_url;
	}
?>