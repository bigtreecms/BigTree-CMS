<?
	header("Content-type: text/javascript");
	$admin->requireLevel(1);
	$admin->deleteUser($_POST["id"]);
?>
$("#row_<?=$_POST["id"]?>").remove();
BigTree.growl("Users","Deleted User");