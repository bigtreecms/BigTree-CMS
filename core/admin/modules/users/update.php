<?
	$success = $admin->updateUser($_POST["id"],$_POST);
	
	if (!$success) {
		$_SESSION["bigtree_admin"]["update_user"] = $_POST;
		$admin->growl("Users","Update Failed","error");
		BigTree::redirect(ADMIN_ROOT."users/edit/".end($bigtree["path"])."/");
	}
	
	$admin->growl("Users","Updated User");
	
	BigTree::redirect(ADMIN_ROOT."users/");
?>