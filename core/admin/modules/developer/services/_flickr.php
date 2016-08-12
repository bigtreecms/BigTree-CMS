<?php
	namespace BigTree;

	$api = new Flickr\API;
	$name = "Flickr";
	$route = "flickr";
	$key_name = "Key";
	$secret_name = "Secret";
	$show_test_environment = false;
	$instructions = array(
		Text::translate('<a href=":flickr_link:" target="_blank">Create a Flickr app</a> in The App Garden.', false, array(":flickr_link:" => "http://www.flickr.com/services/apps/create/apply/")),
		Text::translate('Enter your Key and Secret that you receive below.'),
		Text::translate('Follow the OAuth process of allowing BigTree/your application access to your Flickr account.')
	);

	$bigtree["api_return_function"] = function(Flickr\API &$api) {
		// Get user ID
		$user = $api->callUncached("flickr.test.login");
		// Get user info
		$user = $api->callUncached("flickr.people.getInfo",array("user_id" => $user->user->id));
		
		// If they don't have a user image we get a busted link, so give them the default BigTree gravatar instead
		if (!$user->person->iconfarm) {
			$user_icon = ADMIN_ROOT."images/icon_default_gravatar.jpg";
		} else {
			$user_icon = "http://farm".$user->person->iconfarm.".staticflickr.com/".$user->person->iconserver."/buddyicons/".$user->person->id.".jpg";
		}
		
		// Save token information and some user info for displaying connection info in the admin.
		$api->Settings["user_id"] = $user->person->id;
		$api->Settings["user_name"] = $user->person->username->_content;
		$api->Settings["user_image"] = $user_icon;
	};