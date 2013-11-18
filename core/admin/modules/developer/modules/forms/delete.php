<?
	$admin->deleteModuleForm(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>