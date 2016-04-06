<?php
	namespace BigTree;
	
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);
	$id = $admin->createModuleReport($module,$title,$table,$type,$filters,$fields,$parser,$view);
	$report_route = $admin->createModuleAction($module,$title,$admin->uniqueModuleActionRoute($module,"report"),"on","export","report-$id");

	$admin->growl("Developer","Created Module Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/$module/");
	