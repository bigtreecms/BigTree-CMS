<?
	$admin->verifyCSRFToken();
	$admin->createCalloutGroup($_POST["name"],$_POST["callouts"]);
	$admin->growl("Developer","Created Callout Group");
	
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
?>