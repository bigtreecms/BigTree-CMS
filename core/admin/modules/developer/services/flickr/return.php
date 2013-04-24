<?
	
	$admin->requireLevel(1);
	$ok = false;
	
	if ($flickrAPI->Client->Process()) {
		if ($flickrAPI->Client->access_token) {
			// TEST LOGIN
			$params = array(
				'method' => 'flickr.test.login',
				'format' => 'json',
				'nojsoncallback' => '1'
			);
			$flickrAPI->Client->CallAPI('http://api.flickr.com/services/rest/', "GET", $params, array('FailOnAccessError'=>true), $user);
			$user = $user->user;
			
			// GET USER IMAGE
			$params = array(
				'method' => 'flickr.people.getInfo',
				'format' => 'json',
				'nojsoncallback' => '1',
				'user_id' => $user->id
			);
			$flickrAPI->Client->CallAPI('http://api.flickr.com/services/rest/', "GET", $params, array('FailOnAccessError'=>true), $user2);
			$user2 = $user2->person;
			
			// UPDATE SETTINGS
			$flickrAPI->settings["token"] = $flickrAPI->Client->access_token;
			$flickrAPI->settings["token_secret"] = $flickrAPI->Client->access_token_secret;
			
			$flickrAPI->settings["user_id"] = $user->id;
			$flickrAPI->settings["user_name"] = $user->username->_content;
			$flickrAPI->settings["user_image"] = "http://farm" . $user2->iconfarm . ".staticflickr.com/" . $user2->iconserver . "/buddyicons/" . $user->id . ".jpg";
			
			$flickrAPI->saveSettings();
			
			$admin->growl("Flickr API","API Connected");
			BigTree::redirect($mroot);
			
			$ok = true;
		}
	}
	
	if (!$ok) {
		$admin->growl("Flickr API","API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>