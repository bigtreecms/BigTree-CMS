<?
	header("Content-type: text/javascript");
	$admin->unignore404($_POST["id"]);
?>
BigTree.growl("Pages","Unignored 404 URL");