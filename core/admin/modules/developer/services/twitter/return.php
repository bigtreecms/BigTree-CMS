<?
	$twitter = new BigTreeTwitterAPI;
	if ($twitter->OAuthClient->Process()) {
		if ($twitter->OAuthClient->access_token) {
			// Get user information
			$user = $twitter->OAuthClient->CallAPI($twitter->URL."account/verify_credentials.json");
			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-twitter-api",array(
				"key" => $twitter->Settings["key"],
				"secret" => $twitter->Settings["secret"],
				"token" => $twitter->OAuthClient->access_token,
				"token_secret" => $twitter->OAuthClient->access_token_secret,
				"user_id" => $user->id,
				"user_name" => $user->screen_name,
				"user_image" => $user->profile_image_url
			));
	
			$admin->growl("Twitter API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/twitter/");
		}
	}
	
	$admin->growl("Twitter API","Unknown Error");
	BigTree::redirect(DEVELOPER_ROOT."services/twitter/");
?>