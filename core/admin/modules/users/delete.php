<?php
	namespace BigTree;
	
	$id = intval($bigtree["commands"][0]);
	$admin->requireLevel(1);

	if ($id == $admin->ID) {
		$admin->growl("Users","You cannot delete your own user.","error");
	} else {
		$user = new User($id);

		// If this person has higher access levels than the person trying to update them, fail.
		if ($user->Level > $admin->Level) {
			$admin->growl("Users","Deleting User Failed","error");
		} else {
			$user->delete();
			$admin->growl("Users","Deleted User");
		}
	}
	
	Router::redirect(ADMIN_ROOT."users/");
	