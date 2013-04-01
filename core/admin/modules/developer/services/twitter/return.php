<?
	
	$admin->requireLevel(1);
	
	print_r($twitterAPI->Client);
	print_r($_REQUEST);
	
	echo $twitterAPI->Client->access_token;
	
	die();
	
	if (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
		$_SESSION['oauth_status'] = 'oldtoken';
		BigTree::redirect($mroot . "connect/");
	}
	
	unset($_SESSION['oauth_token']);
	unset($_SESSION['oauth_token_secret']);
	
	if (200 == $connection->http_code) {
		$settings["token"] = $access_token["oauth_token"];
		$settings["token_secret"] = $access_token["oauth_token_secret"];
		$settings["user_id"] = $access_token["user_id"];
		$settings["user_name"] = $access_token["screen_name"];
		
		$admin->updateSettingValue("bigtree-internal-twitter-api", $settings);
		
		$admin->growl("Twitter API","Token Updated");
		BigTree::redirect($mroot);
	} else {
		BigTree::redirect($mroot . "connect/");
	}

?>