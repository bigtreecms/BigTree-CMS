<?php
	namespace BigTree;
	
	Globalize::POST();
	
	// Let's see if the ID has already been used.
	if (SQL::exists("bigtree_templates",$id)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	} elseif (!$admin->createTemplate($id,$name,$routed,$level,$module,$resources)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	}

	Utils::growl("Developer","Created Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	