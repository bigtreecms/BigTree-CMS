<?
	$admin->deleteModule(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module");
	BigTree::redirect(DEVELOPER_ROOT."modules/");
?>