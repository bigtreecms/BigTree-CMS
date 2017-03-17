<?
	$admin->verifyCSRFToken();
	
	BigTree::globalizePOSTVars();

	$form_id = end($bigtree["path"]);
	$admin->updateModuleForm($form_id,$title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
	$action = $admin->getModuleActionForForm($form_id);

	$admin->growl("Developer","Updated Module Form");

	if ($_POST["return_page"]) {
		BigTree::redirect($_POST["return_page"]);
	} else {
		BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
	}
?>