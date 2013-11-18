<?
	$admin->deleteModuleAction(end($bigtree["path"]));
	$admin->growl("Developer","Deleted Action");
	
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>