<?
	$admin->verifyCSRFToken();

	$admin->deleteTemplate($_GET["id"]);
	
	$admin->growl("Developer","Deleted Template");
	BigTree::redirect(DEVELOPER_ROOT."templates/");
?>