<?
	$token = $api->oAuthSetToken($_GET["code"]);
	if ($api->OAuthError) {
		$admin->growl("Google Cloud Storage",$api->OAuthError,"error");
	} else {
		$admin->growl("Google Cloud Storage","Connected");
	}
	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/google/");
?>