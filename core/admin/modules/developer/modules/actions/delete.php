<?php
	namespace BigTree;
	
	$admin->deleteModuleAction(end($bigtree["path"]));
	Utils::growl("Developer","Deleted Action");
	
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	