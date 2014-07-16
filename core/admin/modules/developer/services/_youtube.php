<?
	$api = new BigTreeYouTubeAPI;
	$name = "YouTube";
	$route = "youtube";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$instructions = array(
		'Login to the <a href="https://console.developers.google.com">Google Developers Console</a> and create a project.',
		'Expand "API &amp; Auth" on the left and click "APIs". Switch the "YouTube Data API v3" API toggle to ON.',
		'Click into the "Credentials" section and click the "Create New Client ID" button.',
		'Choose "Web Application" for the Application Type. Enter '.DEVELOPER_ROOT.'services/youtube/return/ as the "Authorized redirect URI".',
		'Enter the Client ID and Client Secret that was created from the previous step below.',
		'Follow the OAuth process of allowing BigTree/your application access to your YouTube account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$info = $api->getChannel();
		$api->Settings["user_id"] = $info->ID;
		$api->Settings["user_name"] = $info->Title;
		$api->Settings["user_image"] = $info->Images->Default;
	}
?>