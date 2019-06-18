<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */
	
	$analytics->oAuthSetToken($_GET["code"]);
	
	if ($analytics->OAuthError) {
		Admin::growl("Google Analytics", $analytics->OAuthError, "error");
		Router::redirect(MODULE_ROOT."configure/");
	} else {
		Admin::growl("Analytics", "Successfully Authenticated");
		Router::redirect(MODULE_ROOT);
	}
	