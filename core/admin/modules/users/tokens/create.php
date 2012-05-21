<?
	$admin->requireLevel(2);
	$admin->createAPIToken($_POST["user"],$_POST["read_only"]);
	$admin->growl("Users","Added API Token");
	
	header("Location: ".$admin_root."users/tokens/");
	die();
?>