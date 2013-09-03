<?
	$api = new BigTreeFlickrAPI;
	$name = "Flickr";
	$route = "flickr";
	$key_name = "Key";
	$secret_name = "Secret";
	$show_test_environment = false;
	$instructions = array(
		'<a href="http://www.flickr.com/services/apps/create/apply/" target="_blank">Create a Flickr app</a> in The App Garden.',
		'Enter your Key and Secret that you receive below.',
		'Follow the OAuth process of allowing BigTree/your application access to your Flickr account.'
	);

	function __localBigTreeAPIReturn(&$api) {
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
	}
?>