<?
	$admin->updateProfile($_POST);
	$admin->growl("Users","Updated Profile");
	BigTree::redirect(ADMIN_ROOT."dashboard/");
?>