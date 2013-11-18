<?
	$admin->deleteSetting(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Setting");
	BigTree::redirect(DEVELOPER_ROOT."settings/");
?>