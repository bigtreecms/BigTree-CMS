<?
	$admin->updateFieldType($_POST["id"],$_POST["name"],$_POST["pages"],$_POST["modules"],$_POST["callouts"]);
	
	$admin->growl("Developer","Updated Field Type");
	header("Location: ../view/");
	die();
?>