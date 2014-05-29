<?
	$admin->requireLevel(2);
	$admin->updateAPIToken(end($path),$_POST["user"],$_POST["read_only"]);
	$admin->growl("Users","Updated API Token");
	
	BigTree::redirect("Location: ".ADMIN_ROOT."users/tokens/");
?>