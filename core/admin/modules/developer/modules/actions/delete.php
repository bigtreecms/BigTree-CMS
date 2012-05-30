<?
	$action = $admin->getModuleAction(end($bigtree["path"]));
	$admin->deleteModuleAction(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Action");
	header("Location: ".$developer_root."modules/edit/".$action["module"]."/");
	die();
?>