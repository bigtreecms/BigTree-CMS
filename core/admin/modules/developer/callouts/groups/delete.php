<?
	$admin->verifyCSRFToken();
	$admin->deleteCalloutGroup($_GET["id"]);
	$admin->growl("Developer","Deleted Callout Group");

	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
?>