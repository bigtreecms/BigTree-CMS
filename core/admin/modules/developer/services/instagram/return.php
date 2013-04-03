<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($instagramAPI->Client->Process()) {
		if ($instagramAPI->Client->access_token) {
			$instagramAPI->Client->CallAPI($instagramAPI->URL."users/self", "GET", array(), array('FailOnAccessError' => true), $user);
			
			// UPDATE SETTINGS
			$instagramAPI->settings["token"] = $instagramAPI->Client->access_token;
			
			$instagramAPI->settings["user_id"] = $user->data->id;
			$instagramAPI->settings["user_name"] = $user->data->username;
			$instagramAPI->settings["user_image"] = $user->data->profile_picture;
			
			$instagramAPI->saveSettings();
			
			$admin->growl("Instagram API","API Connected");
			BigTree::redirect($mroot);
			
			$ok = true;
		}
	}
	
	if (!$ok) {
		$admin->growl("Instagram API","API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>