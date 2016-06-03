<?php
	namespace BigTree;
	
	$admin->deleteFieldType(end($bigtree["path"]));
	
	Utils::growl("Developer","Deleted Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	