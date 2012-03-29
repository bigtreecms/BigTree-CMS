<?
	header("Content-type: text/javascript");
	$admin->requireLevel(1);
	$admin->deleteUser($_POST["id"]);
?>
BigTree.growl("Users","Deleted User");