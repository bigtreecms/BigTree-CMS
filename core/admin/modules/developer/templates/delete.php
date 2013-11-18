<?
	$admin->deleteTemplate(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Template");
	BigTree::redirect(DEVELOPER_ROOT."templates/");
?>