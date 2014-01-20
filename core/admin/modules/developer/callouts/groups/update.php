<?
	$admin->updateCalloutGroup(end($bigtree["path"]),$_POST["name"]);
	$admin->growl("Developer","Updated Callout Group");
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
?>