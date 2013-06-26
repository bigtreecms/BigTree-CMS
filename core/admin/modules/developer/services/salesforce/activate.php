<?
	$admin->updateSettingValue("bigtree-internal-salesforce-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$salesforce = new BigTreeSalesforceAPI;
	$salesforce->OAuthClient->Process();

	if ($salesforce->OAuthClient->authorization_error) {
		if ($salesforce->OAuthClient->authorization_error == "it was not possible to access the OAuth request token: it was returned an unexpected response status 401 Response: Failed to validate oauth signature and token") {
			$admin->growl("Salesforce API","Invalid Secret/Key","error");
		} elseif (strpos($salesforce->OAuthClient->authorization_error,"Desktop applications only support the oauth_callback value 'oob'") !== false) {
			$admin->growl("Salesforce API","Invalid Callback URL","error");
		} else {
			$admin->growl("Salesforce API","Unknown Error","error");
		}
		BigTree::redirect(DEVELOPER_ROOT."services/salesforce/");
	}
?>