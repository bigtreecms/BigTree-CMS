<?
	$admin->deleteFieldType(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Field Type");
	BigTree::redirect(DEVELOPER_ROOT."field-types/");
?>