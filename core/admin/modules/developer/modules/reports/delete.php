<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleReport($_GET["id"]);

	$admin->growl("Developer","Deleted Report");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>