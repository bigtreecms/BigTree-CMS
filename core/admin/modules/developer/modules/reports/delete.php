<?php
	namespace BigTree;
	
	$admin->deleteModuleReport(end($bigtree["commands"]));

	Utils::growl("Developer","Deleted Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	