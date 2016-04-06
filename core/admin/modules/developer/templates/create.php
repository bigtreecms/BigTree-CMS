<?php
	namespace BigTree;
	
	BigTree::globalizePOSTVars();
	
	// Let's see if the ID has already been used.
	if ($cms->getTemplate($id)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	} elseif (!$admin->createTemplate($id,$name,$routed,$level,$module,$resources)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	}

	$admin->growl("Developer","Created Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	