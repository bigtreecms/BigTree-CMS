<?
	$admin->updateFieldType($_POST["id"],$_POST["name"],$_POST["pages"],$_POST["modules"],$_POST["callouts"],$_POST["settings"]);
	
	$admin->growl("Developer","Updated Field Type");
	BigTree::redirect($developer_root."field-types/");
?>