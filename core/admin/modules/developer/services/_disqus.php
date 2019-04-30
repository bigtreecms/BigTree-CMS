<?php
	namespace BigTree;

	$api = new Disqus\API;
	$name = "Disqus";
	$route = "disqus";
	$key_name = "Public Key <small>API Key</small>";
	$secret_name = "Secret Key <small>API Secret</small>";
	$show_test_environment = false;
	$instructions = [
		Text::translate('<a href=":disqus_link:" target="_blank">Register a Disqus application</a>.', false, [":disqus_link:" => "http://disqus.com/api/applications/register/"]),
		Text::translate('Enter your Public Key and Secret Key that you receive below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Disqus account.')
	];

	$bigtree["api_return_function"] = function(Disqus\API &$api) {
		$user = $api->getUser();
		$api->Settings["user_name"] = $user->Name;
		$api->Settings["user_image"] = $user->Image;
		$api->Settings["user_id"] = $user->ID;
	};