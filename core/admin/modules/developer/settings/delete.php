<?
	$admin->deleteSetting(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Setting");
	BigTree::redirect($developer_root."settings/");
?>