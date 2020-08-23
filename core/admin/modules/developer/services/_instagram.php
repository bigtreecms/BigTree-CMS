<?php
	$api = new BigTreeInstagramAPI;
	$name = "Instagram Basic Display";
	$route = "instagram";
	$key_name = "Instagram App ID";
	$secret_name = "Instagram App Secret";
	$show_test_environment = false;
	
	if (!BigTree::getIsSSL()) {
		$admin->stop('<strong style="color: red">You must serve the BigTree admin interface over HTTPS to setup the Instagram Basic Display API.</strong>');
	} else {
		$instructions = [
			'Create an <a href="https://developers.facebook.com/docs/instagram-basic-display-api/getting-started" target="_blank">Instagram Application</a> at the Facebook for Developers portal using the instructions they provide.',
			'Add the following as a valid OAuth Redirect URI: '.ADMIN_ROOT.'developer/services/instagram/return/',
			'Add the following as the Deauthorize Callback URL: '.ADMIN_ROOT.'developer/services/instagram/disconnect/',
			'Add the following as the Data Deletion Request URL: '.ADMIN_ROOT,
			'Enter the application\'s "App ID" and "App Secret" below.',
			'Follow the OAuth process of allowing BigTree/your application access to your Instagram account.'
		];
	}

	function __localBigTreeAPIReturn(&$api) {
		$user = $api->callUncached("me", ["fields" => "id, username"]);
		$api->Settings["user_id"] = $user->id;
		$api->Settings["user_name"] = $user->username;
		$api->saveSettings();
	}
