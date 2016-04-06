<?php
	namespace BigTree;
	
	$admin->updateModuleGroup(end($bigtree["path"]),$_POST["name"]);	

	$admin->growl("Developer","Updated Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	