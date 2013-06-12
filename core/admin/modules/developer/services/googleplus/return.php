<?
	$googleplus = new BigTreeGooglePlusAPI;
	if ($googleplus->OAuthClient->Process()) {
		if ($googleplus->OAuthClient->access_token) {
			// Get user information directly from Google's OAuth system
			$googleplus->OAuthClient->CallAPI('https://www.googleapis.com/oauth2/v1/userinfo', "GET", array(), array('FailOnAccessError'=>true), $user);
			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-googleplus-api",array(
				"key" => $googleplus->Settings["key"],
				"secret" => $googleplus->Settings["secret"],
				"token" => $googleplus->OAuthClient->access_token,
				"user_id" => $user->id,
				"user_name" => $user->name,
				"user_image" => $user->picture
			));
	
			$admin->growl("Google+ API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/googleplus/");
		}
	}
	
	$admin->growl("Google+ API","Unknown Error");
	BigTree::redirect(DEVELOPER_ROOT."services/googleplus/");
?>