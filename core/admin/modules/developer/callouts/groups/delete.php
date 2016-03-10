<?php
	BigTree\CalloutGroup::delete(end($bigtree["path"]));

	$admin->growl("Developer","Deleted Callout Group");
	BigTree::redirect(DEVELOPER_ROOT."callouts/groups/");
	