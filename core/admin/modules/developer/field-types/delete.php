<?
	$admin->deleteFieldType(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Field Type");
	header("Location: ../../view/");
	die();
?>