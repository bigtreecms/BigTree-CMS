<?
	$token = $api->oAuthSetToken($_GET["code"]);
	if ($api->OAuthError) {
		$admin->growl("$name API",$api->OAuthError,"error");
	} else {
		$user = $api->getUser();
		$api->Settings["user_name"] = $user->Name;
		$api->Settings["user_image"] = $user->Image;
		$api->Settings["user_id"] = $user->ID;
		$api->saveSettings();
		$admin->growl("$name API","Connected");
	}
	BigTree::redirect(DEVELOPER_ROOT."services/$route/");
?>