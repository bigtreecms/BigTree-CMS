<?
	
	$admin->requireLevel(1);
	
	if (!$_POST["key"] && !$_POST["key"]) {
		BigTree::redirect($mroot . "configure/");
	}
	
	$twitterAPI->settings["key"] = $_POST["key"];
	$twitterAPI->settings["secret"] = $_POST["secret"];
	
	// RESET VALS
	$twitterAPI->settings["token"] = "";
	$twitterAPI->settings["token_secret"] = "";
	$twitterAPI->settings["user_id"] = "";
	$twitterAPI->settings["user_name"] = "";
	$twitterAPI->settings["user_image"] = "";
	
	$twitterAPI->saveSettings();
	
	// CLEAR OLD ATTEMPTS
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	
	$admin->growl("Twitter API", "Consumer Values Saved");
	BigTree::redirect($mroot . "connect/");
	
?>