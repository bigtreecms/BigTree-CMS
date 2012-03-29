<?
	$admin->deleteModule(end($path));
	
	$admin->growl("Developer","Deleted Module");
	header("Location: ".$developer_root."modules/view/");
	die();
?>