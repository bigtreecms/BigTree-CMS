<?
	$admin->verifyCSRFToken();
	$admin->deleteModuleEmbedForm($_GET["id"]);

	$admin->growl("Developer","Deleted Embeddable Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>