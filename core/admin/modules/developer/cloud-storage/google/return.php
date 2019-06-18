<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Google $google
	 */
	
	$google->oAuthSetToken($_GET["code"]);
	
	if ($google->OAuthError) {
		Admin::growl("Google Cloud Storage", $google->OAuthError, "error");
	} else {
		Admin::growl("Google Cloud Storage", "Connected");
	}
	
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	