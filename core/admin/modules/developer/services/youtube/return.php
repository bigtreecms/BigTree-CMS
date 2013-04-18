<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($youtubeAPI->Client->Process()) {
		
		if ($youtubeAPI->Client->access_token) {
			//$youtubeAPI->Client->CallAPI('https://www.googleapis.com/oauth2/v1/userinfo', "GET", array(), array('FailOnAccessError'=>true), $user);
			
			// UPDATE SETTINGS
			$youtubeAPI->settings["token"] = $youtubeAPI->Client->access_token;
			
			$youtubeAPI->settings["access_token_expiry"] = $youtubeAPI->Client->access_token_expiry;
			$youtubeAPI->settings["refresh_token"] = $youtubeAPI->Client->refresh_token;
			
			$youtubeAPI->settings["user_id"] = $user->id;
			$youtubeAPI->settings["user_name"] = $user->name;
			$youtubeAPI->settings["user_image"] = $user->picture;
			
			$youtubeAPI->saveSettings();
			
			$admin->growl("YouTube API","API Connected");
			BigTree::redirect($mroot);
			
			$ok = true;
		}
	}
	
	if (!$ok) {
		$admin->growl("YouTube API","API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>