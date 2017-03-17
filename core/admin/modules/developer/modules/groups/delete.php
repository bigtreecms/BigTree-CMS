<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleGroup($_GET["id"]);
	
	$admin->growl("Developer","Deleted Module Group");
	BigTree::redirect(DEVELOPER_ROOT."modules/groups/");
?>