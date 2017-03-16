<?
	header("Content-type: text/javascript");
	
	$admin->verifyCSRFToken();
	$admin->unignore404($_POST["id"]);
?>