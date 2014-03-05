<?
	BigTree::globalizePOSTVars();

	$module = $admin->getModule(end($bigtree["path"]));
	$id = $admin->createModuleReport($module,$title,$table,$type,$filters,$fields,$parser,$view);
	$report_route = $admin->createModuleAction($module["id"],$title,$admin->uniqueModuleActionRoute($module["id"],"report"),"on","export",false,false,$id);

	$admin->growl("Developer","Created Module Report");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$module["id"]."/");
?>