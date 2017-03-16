<?
	$admin->verifyCSRFToken();
	$admin->updateCalloutGroup(end($bigtree["path"]),$_POST["name"],$_POST["callouts"]);
	$admin->growl("Developer","Updated Callout Group");
	
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
?>