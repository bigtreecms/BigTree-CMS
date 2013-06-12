<?
	$instagram = new BigTreeInstagramAPI;
	if ($instagram->OAuthClient->Process()) {
		if ($instagram->OAuthClient->access_token) {
			// Get user information
			$user = $instagram->OAuthClient->CallAPI($instagram->URL."users/self");
			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-instagram-api",array(
				"key" => $instagram->Settings["key"],
				"secret" => $instagram->Settings["secret"],
				"token" => $instagram->OAuthClient->access_token,
				"user_id" => $user->data->id,
				"user_name" => $user->data->username,
				"user_image" => $user->data->profile_picture
			));
	
			$admin->growl("Instagram API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/instagram/");
		}
	}
	
	$admin->growl("Instagram API","Unknown Error");
	BigTree::redirect(DEVELOPER_ROOT."services/instagram/");
?>