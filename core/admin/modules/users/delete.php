<?php
	$id = intval($bigtree["commands"][0]);
	$admin->requireLevel(1);

	if ($id == $admin->ID) {
		$admin->growl("Users","You cannot delete your own user.","error");
	} else {
		if ($admin->deleteUser($id)) {
			$admin->growl("Users","Deleted User");
		} else {
			$admin->growl("Users","Deleting User Failed","error");
		}
	}
	BigTree::redirect(ADMIN_ROOT."users/");