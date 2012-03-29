<?
	$admin->updateCallout($_POST["id"],$_POST["name"],$_POST["description"],$_POST["level"],$_POST["resources"]);

	$admin->growl("Developer","Updated Callout");
	header("Location: ".$developer_root."callouts/view/");
	die();
?>