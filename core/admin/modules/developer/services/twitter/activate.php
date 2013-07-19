<?
	$admin->updateSettingValue("bigtree-internal-twitter-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$twitter = new BigTreeTwitterAPI;
	$twitter->OAuthClient->Process();

	if ($twitter->OAuthClient->authorization_error) {
		if ($twitter->OAuthClient->authorization_error == "it was not possible to access the OAuth request token: it was returned an unexpected response status 401 Response: Failed to validate oauth signature and token") {
			$admin->growl("Twitter API","Invalid Secret/Key","error");
		} elseif (strpos($twitter->OAuthClient->authorization_error,"Desktop applications only support the oauth_callback value 'oob'") !== false) {
			$admin->growl("Twitter API","Invalid Callback URL","error");
		} else {
			$admin->growl("Twitter API","Unknown Error","error");
		}
		BigTree::redirect(DEVELOPER_ROOT."services/twitter/");
	}
?>