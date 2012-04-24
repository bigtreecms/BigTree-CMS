<?
	$action = $admin->getModuleAction(end($path));
	$admin->deleteModuleAction(end($path));
	
	$admin->growl("Developer","Deleted Action");
	header("Location: ".$developer_root."modules/edit/".$action["module"]."/");
	die();
?>