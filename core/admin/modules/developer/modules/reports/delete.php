<?php
	namespace BigTree;
	
	$admin->deleteModuleReport(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	