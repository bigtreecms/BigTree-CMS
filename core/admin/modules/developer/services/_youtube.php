<?
	$api = new BigTreeYouTubeAPI;
	$name = "YouTube";
	$route = "youtube";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$instructions = array(
		'Login to the <a href="https://cloud.google.com/console/">Google Cloud Console</a> and create a project.',
		'Click into the project and enter the "API &amp; auth" section. Enable access to the YouTube Data API.',
		'Click into the "Credentials" section and click the "Create New Client ID" button.',
		'Enter '.DEVELOPER_ROOT.'services/youtube/return/ as an "Authorized redirect URI" and choose "Web Application" for the Application Type.',
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