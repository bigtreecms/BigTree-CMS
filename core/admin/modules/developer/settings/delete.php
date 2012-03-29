<?
	$admin->deleteSetting(end($path));
	
	$admin->growl("Developer","Deleted Setting");
	header("Location: ".$developer_root."settings/view/");
	die();
?>