<?
	header("Content-type: text/javascript");
	$id = intval($_POST["id"]);

	$admin->verifyCSRFToken();
	$admin->requireLevel(1);
	$admin->deleteUser($id);
?>
$("#row_<?=$id?>").remove();
BigTree.growl("Users","Deleted User");