<?php
	namespace BigTree;

	$api = new GooglePlus\API;
	$name = "Google+";
	$route = "googleplus";
	$key_name = "Client ID";
	$secret_name = "Client Secret";
	$show_test_environment = false;
	$instructions = array(
		Text::translate('Login to the <a href=":google_dev_link:" target="_blank">Google Developers Console</a> and create a project.', false, array(":google_dev_link:" => "https://console.developers.google.com")),
		Text::translate('Expand "API &amp; Auth" on the left and click "APIs". Choose the "Google+ API" API and enable the API.'),
		Text::translate('Click into the "Credentials" section and click the "Add Credentials" button, choosing OAuth 2.0 client ID.'),
		Text::translate('Choose "Web Application" for the Application Type. Enter :redirect_uri: as the "Authorized redirect URI".', false, array(":redirect_uri:" => DEVELOPER_ROOT.'services/googleplus/return/')),
		Text::translate('Enter the Client ID and Client Secret that was created from the previous step below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Google+ account.')
	);

	$bigtree["api_return_function"] = function(&$api) {
		$info = $api->getPerson();
		$api->Settings["user_id"] = $info->ID;
		$api->Settings["user_name"] = $info->DisplayName;
		$api->Settings["user_image"] = $info->Image;
	};