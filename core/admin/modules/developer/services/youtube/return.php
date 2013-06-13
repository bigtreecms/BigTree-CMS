<?
	$youtube = new BigTreeYouTubeAPI;
	if ($youtube->OAuthClient->Process()) {
		if ($youtube->OAuthClient->access_token) {
			$setting = array(
				"key" => $youtube->Settings["key"],
				"secret" => $youtube->Settings["secret"],
				"token" => $youtube->OAuthClient->access_token
			);
			// Get user information directly from YouTube's OAuth system
			$youtube->OAuthClient->CallAPI("https://gdata.youtube.com/feeds/api/users/default?v=2&alt=json", "GET", array(), array('FailOnAccessError'=>true), $user);
			// If the user result is an array then they might not have a YouTube account
			if (is_object($user)) {
				$setting["user_id"] = $user->entry->author[0]->{'yt$userId'}->{'$t'};
				$setting["user_name"] = $user->entry->author[0]->name->{'$t'};
				$setting["user_image"] = $user->entry->{'media$thumbnail'}->url;
			}

			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-youtube-api",$setting);
			$admin->growl("YouTube API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/youtube/");
		}
	}
	
	if (strpos($youtube->OAuthClient->authorization_error,'"invalid_client"') !== false) {
		$admin->growl("YouTube API","Invalid Client ID/Secret","error");
	} else {
		$admin->growl("YouTube API","Unknown Error","error");
	}
	BigTree::redirect(DEVELOPER_ROOT."services/youtube/");
?>