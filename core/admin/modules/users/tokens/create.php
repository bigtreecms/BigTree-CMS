<?
	$admin->requireLevel(2);
	$admin->createAPIToken($_POST["user"],$_POST["read_only"]);
	$admin->growl("Users","Added API Token");
	
	BigTree::redirect("Location: ".ADMIN_ROOT."users/tokens/");
?>