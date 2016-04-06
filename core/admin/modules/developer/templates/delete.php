<?php
	namespace BigTree;
	
	$admin->deleteTemplate(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	