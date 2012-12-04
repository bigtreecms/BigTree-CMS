<?
	$admin->deleteFieldType(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Field Type");
	BigTree::redirect($developer_root."field-types/");
?>