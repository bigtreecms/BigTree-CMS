<?
	$admin->deleteModuleGroup(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module Group");
	BigTree::redirect(DEVELOPER_ROOT."modules/groups/");
?>