<?
	$admin->requireLevel(1);
	$success = $admin->updateUser(end($bigtree["path"]),$_POST);
	
	if (!$success) {
		$_SESSION["bigtree"]["update_user"] = $_POST;
		$admin->growl("Users","Update Failed","error");
		header("Location: ".ADMIN_ROOT."users/edit/".end($bigtree["path"])."/");
		die();
	}
	
	$admin->growl("Users","Updated User");
	
	header("Location: ".ADMIN_ROOT."users/");
	die();
?>