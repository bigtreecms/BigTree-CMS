<?
	$admin->deleteModule(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module");
	header("Location: ".$developer_root."modules/view/");
	die();
?>