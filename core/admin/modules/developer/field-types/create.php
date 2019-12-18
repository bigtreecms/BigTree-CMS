<?php
	$admin->verifyCSRFToken();
	$id = $_POST["id"];
	
	if ($admin->getFieldType($id) || file_exists(SERVER_ROOT."core/admin/field-types/$id/")) {
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		BigTree::redirect(DEVELOPER_ROOT."field-types/add/");
	} elseif (!$admin->createFieldType($_POST["id"],$_POST["name"],$_POST["use_cases"],$_POST["self_draw"])) {
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		BigTree::redirect(DEVELOPER_ROOT."field-types/add/");		
	}
	
	$admin->growl("Developer","Created Field Type");
	BigTree::redirect(DEVELOPER_ROOT."field-types/new/$id/");
