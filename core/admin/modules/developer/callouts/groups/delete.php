<?php
	namespace BigTree;

	$group = new CalloutGroup(end($bigtree["path"]));
	$group->delete();

	$admin->growl("Developer","Deleted Callout Group");
	
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	