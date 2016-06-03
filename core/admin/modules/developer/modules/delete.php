<?php
	namespace BigTree;
	
	$admin->deleteModule(end($bigtree["path"]));
	
	Utils::growl("Developer","Deleted Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
	