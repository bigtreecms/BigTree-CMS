<?
	$admin->deleteModuleForm(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Form");
	BigTree::redirect($developer_root."modules/edit/".$_GET["module"]."/");
?>