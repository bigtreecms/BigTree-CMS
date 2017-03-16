<?
	header("Content-type: text/javascript");
	
	$admin->verifyCSRFToken();
	$admin->ignore404($_POST["id"]);
?>