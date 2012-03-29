<?
	$admin->deleteModuleView(end($commands));
		
	$admin->growl("Developer","Deleted View");
	header("Location: ".$developer_root."modules/edit/".$commands[0]."/");
	die();
?>