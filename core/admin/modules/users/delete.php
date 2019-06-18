<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	Auth::user()->requireLevel(1);
	
	$id = intval($_GET["id"]);
	
	if ($id == Auth::user()->ID) {
		Admin::growl("Users", "You cannot delete your own user.", "error");
	} else {
		$user = new User($id);
		
		// If this person has higher access levels than the person trying to update them, fail.
		if ($user->Level > Auth::user()->Level) {
			Admin::growl("Users", "Deleting User Failed", "error");
		} else {
			$user->delete();
			Admin::growl("Users", "Deleted User");
		}
	}
	
	Router::redirect(ADMIN_ROOT."users/");
	