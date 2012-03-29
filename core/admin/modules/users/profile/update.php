<?
	$admin->updateProfile($_POST);
	$admin->growl("Users","Updated Profile");
	header("Location: ".$admin_root."dashboard/");
	die();
?>