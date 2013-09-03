<?
	$api = new BigTreeTwitterAPI;
	$name = "Twitter";
	$route = "twitter";
	$key_name = "Consumer Key";
	$secret_name = "Consumer Secret";
	$show_test_environment = false;
	$instructions = array(
		'Create a <a href="https://dev.twitter.com/apps" target="_blank">Twitter Application</a> at the Twitter Developers portal.',
		'Set the application\'s callback URL to '.DOMAIN,
		'Enter the application\'s "Consumer Key" and "Consumer Secret" below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Twitter account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->callUncached("account/verify_credentials.json");
		$api->Settings["user_id"] = $user->id;
		$api->Settings["user_name"] = $user->screen_name;
		$api->Settings["user_image"] = $user->profile_image_url;
	}
?>