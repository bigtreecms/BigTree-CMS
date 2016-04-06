<?php
	namespace BigTree;
	
	$admin->deleteModule(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
	