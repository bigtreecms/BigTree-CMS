<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleForm($_GET["id"]);

	$admin->growl("Developer","Deleted Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>