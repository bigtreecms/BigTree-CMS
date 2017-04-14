<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	Auth::user()->requireLevel(1);

	$id = intval($bigtree["commands"][0]);

	if ($id == Auth::user()->ID) {
		Utils::growl("Users","You cannot delete your own user.","error");
	} else {
		$user = new User($id);

		// If this person has higher access levels than the person trying to update them, fail.
		if ($user->Level > Auth::user()->Level) {
			Utils::growl("Users","Deleting User Failed","error");
		} else {
			$user->delete();
			Utils::growl("Users","Deleted User");
		}
	}
	
	Router::redirect(ADMIN_ROOT."users/");
	