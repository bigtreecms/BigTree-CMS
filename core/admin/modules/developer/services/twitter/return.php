<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($twitterAPI->Client->Process()) {
		if ($twitterAPI->Client->access_token) {
			$twitterAPI->Client->CallAPI($twitterAPI->URL."account/verify_credentials.json", "GET", array(), array('FailOnAccessError'=>true), $user);
			
			// UPDATE SETTINGS
			$twitterAPI->settings["token"] = $twitterAPI->Client->access_token;
			$twitterAPI->settings["token_secret"] = $twitterAPI->Client->access_token_secret;
			
			$twitterAPI->settings["user_id"] = $user->id;
			$twitterAPI->settings["user_name"] = $user->screen_name;
			$twitterAPI->settings["user_image"] = $user->profile_image_url;
			
			$twitterAPI->saveSettings();
			
			$admin->growl("Twitter API","API Connected");
			BigTree::redirect($mroot);
			
			$ok = true;
		}
	}
	
	if (!$ok) {
		$admin->growl("Twitter API","API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>