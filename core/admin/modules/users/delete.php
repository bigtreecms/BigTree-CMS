<?php
	$id = intval($bigtree["commands"][0]);
	$admin->requireLevel(1);

	if ($id == $admin->ID) {
		$admin->growl("Users","You cannot delete your own user.","error");
	} else {
		$user = BigTree\User::get($id);

		// If this person has higher access levels than the person trying to update them, fail.
		if ($user["level"] > $admin->Level) {
			$admin->growl("Users","Deleting User Failed","error");
		} else {
			BigTree\User::delete($id);
			$admin->growl("Users","Deleted User");
		}
	}
	BigTree::redirect(ADMIN_ROOT."users/");
	