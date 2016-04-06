<?php
	namespace BigTree;

	$group = new CalloutGroup(end($bigtree["path"]));
	$group->update($_POST["name"],$_POST["callouts"]);

	$admin->growl("Developer","Updated Callout Group");
	
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	