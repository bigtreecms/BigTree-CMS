<?
	$token = $api->oAuthSetToken($_GET["code"]);
	if ($api->OAuthError) {
		$admin->growl("$name API",$api->OAuthError,"error");
	} else {
		__localBigTreeAPIReturn($api);
		$admin->growl("$name API","Connected");
	}
	BigTree::redirect(DEVELOPER_ROOT."services/$route/");
?>