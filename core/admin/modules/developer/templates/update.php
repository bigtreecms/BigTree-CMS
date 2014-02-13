<?
	BigTree::globalizePOSTVars();
	$admin->updateTemplate($id,$name,$level,$module,$resources);
	$admin->growl("Developer","Updated Template");
	BigTree::redirect(DEVELOPER_ROOT."templates/");
?>