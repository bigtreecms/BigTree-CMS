<?
	
	$admin->requireLevel(1);
	
	if (!$_POST["key"] && !$_POST["key"]) {
		BigTree::redirect($mroot . "configure/");
	}
	
	$instagramAPI->settings["key"] = $_POST["key"];
	$instagramAPI->settings["secret"] = $_POST["secret"];
	$instagramAPI->settings["scope"] = $_POST["scope"];
	
	// RESET VALS
	$instagramAPI->settings["token"] = "";
	$instagramAPI->settings["token_secret"] = "";
	$instagramAPI->settings["user_id"] = "";
	$instagramAPI->settings["user_name"] = "";
	$instagramAPI->settings["user_image"] = "";
	
	$instagramAPI->saveSettings();
	
	// CLEAR OLD ATTEMPTS
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	
	$admin->growl("Instagram API", "Client Values Saved");
	BigTree::redirect($mroot . "connect/");
	
?>