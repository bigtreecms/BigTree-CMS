<?
	$admin->verifyCSRFToken();
	$admin->deleteModule($_GET["id"]);
	
	$admin->growl("Developer","Deleted Module");
	BigTree::redirect(DEVELOPER_ROOT."modules/");
?>