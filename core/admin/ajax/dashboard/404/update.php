<?
	$admin->verifyCSRFToken();
	$admin->set404Redirect($_POST["id"],$_POST["value"]);
?>