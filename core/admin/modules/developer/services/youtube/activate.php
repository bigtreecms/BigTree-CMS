<?
	$admin->updateSettingValue("bigtree-internal-youtube-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
		
	// Renew the OAuth setup
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	$youtube = new BigTreeYouTubeAPI;
	$youtube->OAuthClient->Process();

	if ($youtube->OAuthClient->authorization_error) {
		$admin->growl("YouTube API","Invalid Secret/Key","error");
		BigTree::redirect(DEVELOPER_ROOT."services/youtube/");
	}
?>