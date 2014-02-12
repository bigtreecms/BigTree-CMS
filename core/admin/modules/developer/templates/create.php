<?
	BigTree::globalizePOSTVars();
	
	// Let's see if the ID has already been used.
	if ($cms->getTemplate($id)) {
		$_SESSION["bigtree_admin"]["admin_saved"] = $_POST;
		$_SESSION["bigtree_admin"]["admin_error"] = true;
		BigTree::redirect(DEVELOPER_ROOT."templates/add/");
	}
	
	$admin->createTemplate($id,$name,$routed,$level,$module,$resources);	
	$admin->growl("Developer","Created Template");
	BigTree::redirect(DEVELOPER_ROOT."templates/");
?>