<?
	$admin->updateSettingValue("bigtree-internal-flickr-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$flickr = new BigTreeFlickrAPI;
	$flickr->OAuthClient->Process();

	if ($flickr->OAuthClient->authorization_error) {
		$admin->growl("Flickr API","Invalid Secret/Key","error");
		BigTree::redirect(DEVELOPER_ROOT."services/flickr/");
	}
?>