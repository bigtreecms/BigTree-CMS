<?
	if ($_POST["group_new"]) {
		$group = $admin->createCalloutGroup($_POST["group_new"]);
	} else {
		$group = $_POST["group_existing"];
	}
	$admin->updateCallout($_POST["id"],$_POST["name"],$_POST["description"],$_POST["level"],$_POST["resources"],$_POST["display_field"],$_POST["display_default"],$group);

	$admin->growl("Developer","Updated Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");
?>