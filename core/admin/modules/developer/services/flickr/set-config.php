<?
	
	$admin->requireLevel(1);
	
	if (!$_POST["key"] && !$_POST["key"]) {
		BigTree::redirect($mroot . "configure/");
	}
	
	$flickrAPI->settings["key"] = $_POST["key"];
	$flickrAPI->settings["secret"] = $_POST["secret"];
	
	// RESET VALS
	$flickrAPI->settings["token"] = "";
	$flickrAPI->settings["token_secret"] = "";
	$flickrAPI->settings["user_id"] = "";
	$flickrAPI->settings["user_name"] = "";
	$flickrAPI->settings["user_image"] = "";
	
	$flickrAPI->saveSettings();
	
	// CLEAR OLD ATTEMPTS
	unset($_SESSION['OAUTH_STATE']);
	unset($_SESSION['OAUTH_ACCESS_TOKEN']);
	
	$admin->growl("Flickr API", "Client Values Saved");
	BigTree::redirect($mroot . "connect/");
	
?>