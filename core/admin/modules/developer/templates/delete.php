<?
	$admin->deleteTemplate(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Template");
	BigTree::redirect($developer_root."templates/");
?>