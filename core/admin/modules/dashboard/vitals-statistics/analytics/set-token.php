<?php
	namespace BigTree;

	$admin->requireLevel(1);
	
	$token = $analytics->oAuthSetToken($_GET["code"]);
	
	if ($analytics->OAuthError) {
		Utils::growl("Google Analytics",$analytics->OAuthError,"error");
		Router::redirect(MODULE_ROOT."configure/");
	} else {
		Utils::growl("Analytics","Successfully Authenticated");
		Router::redirect(MODULE_ROOT);
	}
	