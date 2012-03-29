<?
	$admin->deleteFieldType(end($path));
	
	$admin->growl("Developer","Deleted Field Type");
	header("Location: ../../view/");
	die();
?>