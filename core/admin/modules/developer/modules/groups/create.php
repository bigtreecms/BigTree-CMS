<?php
	namespace BigTree;
	
	$admin->createModuleGroup($_POST["name"]);
	
	Utils::growl("Developer","Created Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	