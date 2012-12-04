<?
	$admin->updateCallout($_POST["id"],$_POST["name"],$_POST["description"],$_POST["level"],$_POST["resources"],$_POST["display_field"],$_POST["display_default"]);

	$admin->growl("Developer","Updated Callout");
	BigTree::redirect($developer_root."callouts/");
?>