<?
	$admin->verifyCSRFToken();
	
	BigTree::globalizePOSTVars();
	$admin->updateTemplate($id,$name,$level,$module,$resources);
	$admin->growl("Developer","Updated Template");

	if (isset($_POST["return_to_front"])) {
		BigTree::redirect(ADMIN_ROOT."pages/edit/".$_POST["return_to_front"]."/");
	} else {
		BigTree::redirect(DEVELOPER_ROOT."templates/");
	}
?>