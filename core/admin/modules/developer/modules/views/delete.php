<?
	$admin->deleteModuleView(end($bigtree["commands"]));
		
	$admin->growl("Developer","Deleted View");
	header("Location: ".$developer_root."modules/edit/".$bigtree["commands"][0]."/");
	die();
?>