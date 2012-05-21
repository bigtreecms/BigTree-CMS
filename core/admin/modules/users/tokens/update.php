<?
	$admin->requireLevel(2);
	$admin->updateAPIToken(end($path),$_POST["user"],$_POST["read_only"]);
	$admin->growl("Users","Updated API Token");
	
	header("Location: ".$admin_root."users/tokens/");
	die();
?>