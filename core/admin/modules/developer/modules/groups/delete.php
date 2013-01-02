<?
	$admin->deleteModuleGroup(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module Group");
	BigTree::redirect($developer_root."modules/groups/");
?>