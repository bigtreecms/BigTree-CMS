<?php
	namespace BigTree;
	
	Globalize::POST();

	$module = end($bigtree["path"]);
	$id = $admin->createModuleReport($module,$title,$table,$type,$filters,$fields,$parser,$view);
	$action_route = SQL::unique("bigtree_module_actions", "route", "report", array("module" => $module), true);
	$report_route = $admin->createModuleAction($module,$title,$action_route,"on","export",$id);

	$admin->growl("Developer","Created Module Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/$module/");
	