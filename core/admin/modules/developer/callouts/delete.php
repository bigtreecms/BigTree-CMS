<?
	$admin->deleteCallout(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Callout");
	BigTree::redirect($developer_root."callouts/");
?>