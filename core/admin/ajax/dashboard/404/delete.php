<?
	header("Content-type: text/javascript");
	
	$admin->verifyCSRFToken();
	$admin->delete404($_POST["id"]);
?>