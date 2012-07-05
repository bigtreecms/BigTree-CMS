<?
	$admin->requireLevel(1);
	$success = $admin->updateUser(end($bigtree["path"]),$_POST);
	
	if (!$success) {
		$_SESSION["bigtree"]["update_user"] = $_POST;
		$admin->growl("Users","Update Failed","error");
		BigTree::redirect(ADMIN_ROOT."users/edit/".end($bigtree["path"])."/");
	}
	
	$admin->growl("Users","Updated User");
	
	BigTree::redirect(ADMIN_ROOT."users/");
?>