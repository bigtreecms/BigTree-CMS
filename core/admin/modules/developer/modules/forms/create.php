<?php
	namespace BigTree;
	
	\BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);
	$form_id = $admin->createModuleForm($module,$title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
	
	// See if add/edit actions already exist
	$add_route = "add";
	$edit_route = "edit";

	// If we already have add/edit routes, get unique new ones for this form
	if (ModuleAction::exists($module,"add") || ModuleAction::exists($module,"edit")) {
		$add_route = SQL::unique("bigtree_module_actions", "route", $cms->urlify("add $title"), array("module" => $module), true);
		$edit_route = SQL::unique("bigtree_module_actions", "route", $cms->urlify("edit $title"), array("module" => $module), true);
	}

	// Create actions for the form
	$admin->createModuleAction($module,"Add $title",$add_route,"on","add",$form_id);
	$admin->createModuleAction($module,"Edit $title",$edit_route,"","edit",$form_id);

	$admin->growl("Developer","Created Module Form");
	Router::redirect(DEVELOPER_ROOT."modules/edit/$module/");
	