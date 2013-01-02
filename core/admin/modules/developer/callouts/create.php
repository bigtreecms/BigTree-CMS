<?
	$admin->createCallout($_POST["id"],$_POST["name"],$_POST["description"],$_POST["level"],$_POST["resources"],$_POST["display_field"],$_POST["display_default"]);
	
	$admin->growl("Developer","Created Callout");
	BigTree::redirect($developer_root."callouts/");
?>