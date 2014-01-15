<?
	$api = new BigTreeGooglePlusAPI;
	$name = "Google+";
	$route = "googleplus";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = array(
		'Login to the <a href="https://cloud.google.com/console/">Google Cloud Console</a> and create a project.',
		'Click into the project and enter the "API &amp; auth" section. Enable access to the Google+ API.',
		'Click into the "Credentials" section and click the "Create New Client ID" button.',
		'Enter '.DEVELOPER_ROOT.'services/youtube/return/ as an "Authorized redirect URI" and choose "Web Application" for the Application Type.',
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