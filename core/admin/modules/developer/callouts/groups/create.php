<?
	$admin->createCalloutGroup($_POST["name"]);
	$admin->growl("Developer","Created Callout Group");
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
?>