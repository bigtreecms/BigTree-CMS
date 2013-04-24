<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($youtubeAPI->Client->Process()) {
		if ($youtubeAPI->Client->access_token) {
			$youtubeAPI->Client->CallAPI('https://gdata.youtube.com/feeds/api/users/default?v=2&alt=json', "GET", array(), array('FailOnAccessError'=>true), $user);
			
			// UPDATE SETTINGS
			$youtubeAPI->settings["token"] = $youtubeAPI->Client->access_token;
			
			$youtubeAPI->settings["token_expiry"] = $youtubeAPI->Client->access_token_expiry;
			$youtubeAPI->settings["refresh_token"] = $youtubeAPI->Client->refresh_token;
			
			$youtubeAPI->settings["user_id"] = $user->entry->author[0]->{'yt$userId'}->{'$t'};
			$youtubeAPI->settings["user_name"] = $user->entry->author[0]->name->{'$t'};
			$youtubeAPI->settings["user_image"] = $user->entry->{'media$thumbnail'}->url;
			
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