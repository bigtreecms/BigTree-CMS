<?
	$api = new BigTreeInstagramAPI;
	$name = "Instagram";
	$route = "instagram";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = array(
		'Create an <a href="http://instagram.com/developer/clients/register/" target="_blank">Instagram Application</a> at the Instagram developer portal.',
		'Set the application\'s OAuth redirect_uri to '.ADMIN_ROOT.'developer/services/instagram/return/',
		'Enter the application\'s "Client ID" and "Client Secret" below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Instagram account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->callUncached("users/self");
		$api->Settings["user_id"] = $user->data->id;
		$api->Settings["user_name"] = $user->data->username;
		$api->Settings["user_image"] = $user->data->profile_picture;
	}
?>