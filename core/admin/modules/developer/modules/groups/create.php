<?php
	namespace BigTree;
	
	$admin->createModuleGroup($_POST["name"]);
	
	$admin->growl("Developer","Created Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	