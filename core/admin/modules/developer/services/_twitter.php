<?php
	namespace BigTree;

	$api = new Twitter\API;
	$name = "Twitter";
	$route = "twitter";
	$key_name = "API Key";
	$secret_name = "API Secret";
	$show_test_environment = false;
	$instructions = [
		Text::translate('Create a <a href=":twitter_api_link:" target="_blank">Twitter Application</a> at the Twitter Developers portal.', false, [":twitter_api_link:" => "https://apps.twitter.com/"]),
		Text::translate('Set the application\'s Website to :domain: and it\'s Callback URL to :callback_url:', false, [":domain:" => DOMAIN, ":callback_url:" => DEVELOPER_ROOT.'services/twitter/return/']),
		Text::translate('Enter the application\'s "API Key" and "API Secret" below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Twitter account.')
	];

	$bigtree["api_return_function"] = function(Twitter\API &$api) {
		$user = $api->callUncached("account/verify_credentials.json");
		$api->Settings["user_id"] = $user->id;
		$api->Settings["user_name"] = $user->screen_name;
		$api->Settings["user_image"] = $user->profile_image_url;
	};