<?
	$api = new BigTreeYouTubeAPI;
	$name = "YouTube";
	$route = "youtube";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$instructions = array(
		'Login to the <a href="https://code.google.com/apis/console/">Google API Console</a> and enable access to the YouTube API.',
		'Choose the "API Access" tab in the API Console and create an OAuth 2.0 client ID if you have not already done so.',
		'Add '.ADMIN_ROOT.'developer/services/youtube/return/ as an Authorized Redirect URI.',
		'Enter your Client ID and Client Secret from the API Console below.',
		'Follow the OAuth process of allowing BigTree/your application access to your YouTube account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$info = $api->getChannel();
		$api->Settings["user_id"] = $info->ID;
		$api->Settings["user_name"] = $info->Title;
		$api->Settings["user_image"] = $info->Images->Default;
	}
?>