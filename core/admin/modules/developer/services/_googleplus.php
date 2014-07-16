<?
	$api = new BigTreeGooglePlusAPI;
	$name = "Google+";
	$route = "googleplus";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = array(
		'Login to the <a href="https://console.developers.google.com">Google Developers Console</a> and create a project.',
		'Expand "API &amp; Auth" on the left and click "APIs". Switch the Google+ API toggle to ON.',
		'Click into the "Credentials" section and click the "Create New Client ID" button.',
		'Choose "Web Application" for the Application Type. Enter '.DEVELOPER_ROOT.'services/googleplus/return/ as the "Authorized redirect URI".',
		'Enter the Client ID and Client Secret that was created from the previous step below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Google+ account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$info = $api->getPerson();
		$api->Settings["user_id"] = $info->ID;
		$api->Settings["user_name"] = $info->DisplayName;
		$api->Settings["user_image"] = $info->Image;
	}
?>