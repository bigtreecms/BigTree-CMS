<?
	$admin->requireLevel(1);
	$id = $admin->createUser($_POST);	
	
	if (!$id) {
		$_SESSION["bigtree"]["create_user"] = $_POST;
		$admin->growl("Users","Creation Failed","error");
		header("Location: ".ADMIN_ROOT."users/add/");
		die();
	}

	$admin->growl("Users","Added User");
	header("Location: ".ADMIN_ROOT."users/edit/$id/new/");
	die();
?>