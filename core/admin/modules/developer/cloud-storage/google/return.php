<?php
	namespace BigTree;

	$token = $google->oAuthSetToken($_GET["code"]);

	if ($google->OAuthError) {
		Utils::growl("Google Cloud Storage",$google->OAuthError,"error");
	} else {
		Utils::growl("Google Cloud Storage","Connected");
	}

	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	