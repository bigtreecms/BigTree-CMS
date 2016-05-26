<?php
	namespace BigTree;
	
	Globalize::POST();

	$form_id = end($bigtree["path"]);
	$admin->updateModuleForm($form_id,$title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
	$action = $admin->getModuleActionForInterface($form_id);

	$admin->growl("Developer","Updated Module Form");

	if ($_POST["return_page"]) {
		Router::redirect($_POST["return_page"]);
	} else {
		Router::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
	}
	