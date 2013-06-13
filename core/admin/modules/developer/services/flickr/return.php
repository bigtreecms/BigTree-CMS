<?
	$flickr = new BigTreeFlickrAPI;
	if ($flickr->OAuthClient->Process()) {
		if ($flickr->OAuthClient->access_token) {
			$flickr->Connected = true;
			// Get user ID
			$user = $flickr->callAPI(array("method" => "flickr.test.login"));
			// Get user info
			$user = $flickr->callAPI(array("method" => "flickr.people.getInfo","user_id" => $user->user->id));
			// If they don't have a user image we get a busted link, so give them the default BigTree gravatar instead
			if (!$user->person->iconfarm) {
				$user_icon = ADMIN_ROOT."images/icon_default_gravatar.jpg";
			} else {
				$user_icon = "http://farm".$user->person->iconfarm.".staticflickr.com/".$user->person->iconserver."/buddyicons/".$user->person->id.".jpg";
			}

			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-flickr-api",array(
				"key" => $flickr->Settings["key"],
				"secret" => $flickr->Settings["secret"],
				"token" => $flickr->OAuthClient->access_token,
				"token_secret" => $flickr->OAuthClient->access_token_secret,
				"user_id" => $user->person->id,
				"user_name" => $user->person->username->_content,
				"user_image" => $user_icon
			));
	
			$admin->growl("Flickr API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/flickr/");
		}
	}
	
	$admin->growl("Flickr API","Unknown Error");
	BigTree::redirect(DEVELOPER_ROOT."services/flickr/");
?>