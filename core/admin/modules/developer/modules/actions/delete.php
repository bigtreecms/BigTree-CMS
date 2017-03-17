<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleAction($_GET["id"]);
	$admin->growl("Developer","Deleted Action");
	
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>