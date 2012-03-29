<?
	$admin->requireLevel(1);
	$id = $admin->createUser($_POST);	
	
	if (!$id) {
		$_SESSION["bigtree"]["create_user"] = $_POST;
		$admin->growl("Users","Creation Failed","error");
		header("Location: ".$admin_root."users/add/");
		die();
	}

	$admin->growl("Users","Added User");
	header("Location: ".$admin_root."users/edit/$id/new/");
	die();
?>