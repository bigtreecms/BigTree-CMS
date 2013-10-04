<?
	$api = new BigTreeDisqusAPI;
	$name = "Disqus";
	$route = "disqus";
	$key_name = "Public Key <small>API Key</small>";
	$secret_name = "Secret Key <small>API Secret</small>";
	$show_test_environment = false;
	$instructions = array(
		'<a href="http://disqus.com/api/applications/register/" target="_blank">Register a Disqus application</a>.',
		'Enter your Public Key and Secret Key that you receive below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Disqus account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->getUser();
		$api->Settings["user_name"] = $user->Name;
		$api->Settings["user_image"] = $user->Image;
		$api->Settings["user_id"] = $user->ID;
	}
?>