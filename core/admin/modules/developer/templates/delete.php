<?
	$admin->deleteTemplate(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Template");
	header("Location: ".$developer_root."templates/view/");
	die();
?>