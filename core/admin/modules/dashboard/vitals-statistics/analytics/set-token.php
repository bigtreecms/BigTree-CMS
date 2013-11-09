<?
	$admin->requireLevel(1);
	
	$token = $analytics->oAuthSetToken($_GET["code"]);
	if ($analytics->OAuthError) {
		$admin->growl("Google Analytics",$analytics->OAuthError,"error");
		BigTree::redirect($mroot."configure/");
	} else {
		$admin->growl("Analytics","Successfully Authenticated");
		BigTree::redirect($mroot);
	}
?>