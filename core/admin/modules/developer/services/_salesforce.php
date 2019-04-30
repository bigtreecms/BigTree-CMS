<?php
	namespace BigTree;

	$api = new Salesforce\API;
	$name = "Salesforce";
	$route = "salesforce";
	$key_name = "Consumer Key";
	$secret_name = "Consumer Secret";
	$show_test_environment = true;
	$instructions = [
		Text::translate('Make sure that your site is accessible via HTTPS. Salesforce requires your Callback URL to be https://'),
		Text::translate('Create a Salesforce "Connected App" by logging into your Salesforce control panel and heading to "Build &raquo; Create &raquo; Apps" and clicking the "New" button at the bottom by "Connected Apps".'),
		Text::translate('Check off "Enable OAuth Settings"'),
		Text::translate('Set the Callback URL to :callback_url:', false, [":callback_url:" => str_replace("http://","https://",DEVELOPER_ROOT).'services/salesforce/return/']),
		Text::translate('Select all the available OAuth Scopes and Save your application.'),
		Text::translate('Enter the application\'s "Consumer Key" and "Consumer Secret" below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Salesforce account.')
	];

	$bigtree["api_return_function"] = function(&$api) {
		
	};