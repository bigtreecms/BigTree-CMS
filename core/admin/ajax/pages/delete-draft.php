<?
	$admin->verifyCSRFToken();
	$admin->deletePageDraft($_GET["id"]);
?>