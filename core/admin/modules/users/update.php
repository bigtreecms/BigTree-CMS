<?
	$admin->requireLevel(1);
	$success = $admin->updateUser(end($path),$_POST);
	
	if (!$success) {
		$_SESSION["bigtree"]["update_user"] = $_POST;
		$admin->growl("Users","Update Failed","error");
		header("Location: ".$admin_root."users/edit/".end($path)."/");
		die();
	}
	
	$admin->growl("Users","Updated User");
	
	header("Location: ".$admin_root."users/");
	die();
?>