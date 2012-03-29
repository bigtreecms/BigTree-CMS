<?
	header("Content-type: text/javascript");
	$admin->ignore404($_POST["id"]);
?>
BigTree.growl("Pages","Ignored 404 URL");