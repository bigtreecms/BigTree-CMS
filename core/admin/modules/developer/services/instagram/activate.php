<?
	$admin->updateSettingValue("bigtree-internal-instagram-api",array("key" => $_POST["key"],"secret" => $_POST["secret"],"scope" => "basic comments relationships likes"));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$instagram = new BigTreeInstagramAPI;
	$instagram->OAuthClient->Process();

	if ($instagram->OAuthClient->authorization_error) {
		$admin->growl("Instagram API","Invalid Secret/Key","error");
		BigTree::redirect(DEVELOPER_ROOT."services/instagram/");
	}
?>