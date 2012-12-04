<?
	$admin->deleteModule(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module");
	BigTree::redirect($developer_root."modules/");
?>