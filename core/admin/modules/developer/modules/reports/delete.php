<?
	$admin->deleteModuleReport(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Report");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>