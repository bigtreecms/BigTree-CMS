<?
	$admin->verifyCSRFToken();
	$admin->deletePageRevision($_GET["id"]);
?>