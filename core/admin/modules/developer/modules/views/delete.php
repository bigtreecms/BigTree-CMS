<?
	$admin->deleteModuleView(end($bigtree["commands"]));
		
	$admin->growl("Developer","Deleted View");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>