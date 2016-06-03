<?php
	namespace BigTree;
	
	$admin->deleteTemplate(end($bigtree["path"]));
	
	Utils::growl("Developer","Deleted Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	