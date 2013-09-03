<?
	$api = new BigTreeSalesforceAPI;
	$name = "Salesforce";
	$route = "salesforce";
	$key_name = "Consumer Key";
	$secret_name = "Consumer Secret";
	$show_test_environment = true;
	$instructions = array(
		'Make sure that your site is accessible via HTTPS. Salesforce requires your Callback URL to be https://',
		'Create a Salesforce "Connected App" by logging into your Salesforce control panel and heading to "Build &raquo; Create &raquo; Apps" and clicking the "New" button at the bottom by "Connected Apps".',
		'Check off "Enable OAuth Settings"',
		'Set the Callback URL to '.str_replace("http://","https://",DEVELOPER_ROOT).'services/salesforce/return/',
		'Select all the available OAuth Scopes and Save your application.',
		'Enter the application\'s "Consumer Key" and "Consumer Secret" below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Salesforce account.'
	);

	function __localBigTreeAPIReturn(&$api) {
		
	}
?>