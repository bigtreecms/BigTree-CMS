<?
	$admin->updateSettingValue("bigtree-internal-googleplus-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$googleplus = new BigTreeGooglePlusAPI;
	$googleplus->OAuthClient->Process();

	if ($googleplus->OAuthClient->authorization_error) {
		$admin->growl("Google+ API","Invalid Secret/Key","error");
		BigTree::redirect(DEVELOPER_ROOT."services/googleplus/");
	}
?>