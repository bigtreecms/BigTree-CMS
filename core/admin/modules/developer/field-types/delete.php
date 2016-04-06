<?php
	namespace BigTree;
	
	$admin->deleteFieldType(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	