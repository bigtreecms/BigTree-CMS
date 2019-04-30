<?php
	namespace BigTree;

	$api = new Instagram\API;
	$name = "Instagram";
	$route = "instagram";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = [
		Text::translate('Create an <a href=":instagram_url:" target="_blank">Instagram Application</a> at the Instagram developer portal.', false, [":instagram_url:" => "http://instagram.com/developer/clients/register/"]),
		Text::translate('Set the application\'s OAuth redirect_uri to :redirect_uri:', false, [":redirect_uri:" => ADMIN_ROOT.'developer/services/instagram/return/']),
		Text::translate('Enter the application\'s "Client ID" and "Client Secret" below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Instagram account.')
	];

	$bigtree["api_return_function"] = function(Instagram\API &$api) {
		$user = $api->callUncached("users/self");
		$api->Settings["user_id"] = $user->data->id;
		$api->Settings["user_name"] = $user->data->username;
		$api->Settings["user_image"] = $user->data->profile_picture;
	};