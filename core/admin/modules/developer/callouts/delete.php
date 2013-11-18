<?
	$admin->deleteCallout(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");
?>