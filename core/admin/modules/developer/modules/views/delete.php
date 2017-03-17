<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleView($_GET["id"]);
		
	$admin->growl("Developer","Deleted View");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>