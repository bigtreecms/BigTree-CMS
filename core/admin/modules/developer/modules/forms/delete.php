<?
	$admin->deleteModuleForm(end($commands));

	$admin->growl("Developer","Deleted Form");
	header("Location: ".$developer_root."modules/edit/".$commands[0]."/");
	die();
?>