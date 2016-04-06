<?php
	namespace BigTree;
	
	$admin->deleteModuleInterface(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Interface");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	