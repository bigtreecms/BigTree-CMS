<?php
	namespace BigTree;
	
	$admin->deleteModuleGroup(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	