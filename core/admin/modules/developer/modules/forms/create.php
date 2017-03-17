<?
	$admin->verifyCSRFToken();
	
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);
	$form_id = $admin->createModuleForm($module,$title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
	
	// See if add/edit actions already exist
	$add_route = "add";
	$edit_route = "edit";
	// If we already have add/edit routes, get unique new ones for this form
	if ($admin->doesModuleActionExist($module,"add") || $admin->doesModuleActionExist($module,"edit")) {
		$add_route = $admin->uniqueModuleActionRoute($module,$cms->urlify("add $title"));
		$edit_route = $admin->uniqueModuleActionRoute($module,$cms->urlify("edit $title"));
	}
	// Create actions for the form
	$admin->createModuleAction($module,"Add $title",$add_route,"on","add",$form_id);
	$admin->createModuleAction($module,"Edit $title",$edit_route,"","edit",$form_id);

	$admin->growl("Developer","Created Module Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/$module/");
?>