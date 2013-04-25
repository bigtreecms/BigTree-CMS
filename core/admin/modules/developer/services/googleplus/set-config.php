<?
	
	$admin->requireLevel(1);
	
	if (!$_POST["key"] && !$_POST["key"]) {
		BigTree::redirect($mroot . "configure/");
	}
	
	$googleplusAPI->settings["key"] = $_POST["key"];
	$googleplusAPI->settings["secret"] = $_POST["secret"];
	
	// RESET VALS
	$googleplusAPI->settings["token"] = "";
	$googleplusAPI->settings["token_secret"] = "";
	$googleplusAPI->settings["user_id"] = "";
	$googleplusAPI->settings["user_name"] = "";
	$googleplusAPI->settings["user_image"] = "";
	
	$googleplusAPI->settings["token_expiry"] = "";
	$googleplusAPI->settings["refresh_token"] = "";
	
	$googleplusAPI->saveSettings();
	
	// CLEAR OLD ATTEMPTS
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	
	$admin->growl("Google+ API", "Client Values Saved");
	BigTree::redirect($mroot . "connect/");
	
?>