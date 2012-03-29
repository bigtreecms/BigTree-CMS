<?
	$admin->deleteCallout(end($path));
	
	$admin->growl("Developer","Deleted Callout");
	header("Location: ".$developer_root."callouts/view/");
	die();
?>