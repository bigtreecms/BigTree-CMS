<?
	
	$admin->requireLevel(1);
	
	if (!$_POST["key"] && !$_POST["key"]) {
		BigTree::redirect($mroot . "configure/");
	}
	
	$youtubeAPI->settings["key"] = $_POST["key"];
	$youtubeAPI->settings["secret"] = $_POST["secret"];
	
	// RESET VALS
	$youtubeAPI->settings["token"] = "";
	$youtubeAPI->settings["token_secret"] = "";
	$youtubeAPI->settings["user_id"] = "";
	$youtubeAPI->settings["user_name"] = "";
	$youtubeAPI->settings["user_image"] = "";
	
	$youtubeAPI->settings["token_expiry"] = "";
	$youtubeAPI->settings["refresh_token"] = "";
	
	$youtubeAPI->saveSettings();
	
	// CLEAR OLD ATTEMPTS
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	
	$admin->growl("Google+ API", "Client Values Saved");
	BigTree::redirect($mroot . "connect/");
	
?>