<?
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);

	$default_position = isset($default_position) ? $default_position : "";

	$fields = array();
	if (is_array($_POST["type"])) {
		foreach ($_POST["type"] as $key => $val) {
			$field = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["options"][$key]),true);
			$field["type"] = $val;
			$field["title"] = htmlspecialchars($_POST["titles"][$key]);
			$field["subtitle"] = htmlspecialchars($_POST["subtitles"][$key]);
			$fields[$key] = $field;
		}
	}

	$form_id = $admin->createModuleForm($module,$title,$table,$fields,$preprocess,$callback,$default_position,$return_view,$return_url,$tagging);
	
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