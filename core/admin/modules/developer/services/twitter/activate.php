<?
	$admin->updateSettingValue("bigtree-internal-twitter-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$twitter = new BigTreeTwitterAPI;
	$twitter->OAuthClient->Process();

	if ($twitter->OAuthClient->authorization_error) {
		$admin->growl("Twitter API","Invalid Secret/Key","error");
		BigTree::redirect(DEVELOPER_ROOT."services/twitter/");
	}
?>