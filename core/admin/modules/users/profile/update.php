<?php
	$admin->verifyCSRFToken();

	if ($_POST["password"] && !$admin->validatePassword($_POST["password"])) {
		$_SESSION["bigtree_admin"]["update_profile"] = $_POST;
		$admin->growl("Users","Invalid Password","error");
		BigTree::redirect(ADMIN_ROOT."users/profile/");	
	}

	$admin->updateProfile($_POST);
	$admin->growl("Users","Updated Profile");
	BigTree::redirect(ADMIN_ROOT."dashboard/");
