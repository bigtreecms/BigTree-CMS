<?
	$api = new BigTreeGooglePlusAPI;
	$name = "Google+";
	$route = "googleplus";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = array(
		'Login to the <a href="https://code.google.com/apis/console/">Google API Console</a> and enable access to the Google+ API.',
		'Choose the "API Access" tab in the API Console and create an OAuth 2.0 client ID if you have not already done so.',
		'Add '.ADMIN_ROOT.'developer/services/googleplus/return/ as an Authorized Redirect URI.',
		'Enter your Client ID and Client Secret from the API Console below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Google+ account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$info = $api->getPerson();
		$api->Settings["user_id"] = $info->ID;
		$api->Settings["user_name"] = $info->DisplayName;
		$api->Settings["user_image"] = $info->Image;
	}
?>