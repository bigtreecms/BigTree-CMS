<?php
	namespace BigTree;

	CalloutGroup::delete(end($bigtree["path"]));

	$admin->growl("Developer","Deleted Callout Group");
	
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	