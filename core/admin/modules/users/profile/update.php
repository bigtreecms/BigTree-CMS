<?
	$admin->updateProfile($_POST);
	$admin->growl("Users","Updated Profile");
	header("Location: ".ADMIN_ROOT."dashboard/");
	die();
?>