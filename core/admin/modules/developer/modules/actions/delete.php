<?
	$admin->deleteModuleAction(end($path));
	
	$admin->growl("Developer","Deleted Action");
	header("Location: ".$developer_root."modules/edit/".$f["module"]."/");
	die();
?>