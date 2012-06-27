<?
	$admin->deleteModuleForm(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Form");
	header("Location: ".$developer_root."modules/edit/".$bigtree["commands"][0]."/");
	die();
?>