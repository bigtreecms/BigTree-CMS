<?php
	$group = new BigTree\CalloutGroup(end($bigtree["path"]));
	$group->update($_POST["name"],$_POST["callouts"]);

	$admin->growl("Developer","Updated Callout Group");
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
	