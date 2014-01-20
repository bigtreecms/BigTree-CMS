<?
	if ($_POST["group_new"]) {
		$group = $admin->createCalloutGroup($_POST["group_new"]);
	} else {
		$group = $_POST["group_existing"];
	}

	// Let's see if the ID has already been used.
	if ($admin->getCallout($_POST["id"])) {
		$_SESSION["bigtree_admin"]["admin_saved"] = $_POST;
		$_SESSION["bigtree_admin"]["admin_error"] = true;
		BigTree::redirect(DEVELOPER_ROOT."callouts/add/");
	}

	$admin->createCallout($_POST["id"],$_POST["name"],$_POST["description"],$_POST["level"],$_POST["resources"],$_POST["display_field"],$_POST["display_default"],$group);	
	$admin->growl("Developer","Created Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");
?>