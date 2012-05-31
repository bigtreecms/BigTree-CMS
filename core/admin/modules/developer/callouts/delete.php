<?
	$admin->deleteCallout(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Callout");
	header("Location: ".$developer_root."callouts/view/");
	die();
?>