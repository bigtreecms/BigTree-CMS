<?
	$admin->deleteModuleGroup(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module Group");
	header("Location: ".$developer_root."modules/groups/view/");
	die();
?>