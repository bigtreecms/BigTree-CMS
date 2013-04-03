<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($googleplusAPI->Client->Process()) {
		if ($googleplusAPI->Client->access_token) {
			$googleplusAPI->Client->CallAPI('https://www.googleapis.com/oauth2/v1/userinfo', "GET", array(), array('FailOnAccessError'=>true), $user);
			
			// UPDATE SETTINGS
			$googleplusAPI->settings["token"] = $googleplusAPI->Client->access_token;
			
			$googleplusAPI->settings["access_token_expiry"] = $googleplusAPI->Client->access_token_expiry;
			$googleplusAPI->settings["refresh_token"] = $googleplusAPI->Client->refresh_token;
			
			$googleplusAPI->settings["user_id"] = $user->id;
			$googleplusAPI->settings["user_name"] = $user->name;
			$googleplusAPI->settings["user_image"] = $user->picture;
			
			$googleplusAPI->saveSettings();
			
			$admin->growl("Google+ API","API Connected");
			BigTree::redirect($mroot);
			
			$ok = true;
		}
	}
	
	if (!$ok) {
		$admin->growl("Google+ API","API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>