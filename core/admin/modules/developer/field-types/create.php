<?php
	namespace BigTree;
	
	$id = $_POST["id"];
	
	if (SQL::exists("bigtree_field_types",$id) || file_exists("../core/admin/form-field-types/draw/$id.php") || file_exists("../core/admin/form-field-types/process/$id.php")) {
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		Router::redirect(DEVELOPER_ROOT."field-types/add/");
	} elseif (!$admin->createFieldType($_POST["id"],$_POST["name"],$_POST["use_cases"],$_POST["self_draw"])) {
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		Router::redirect(DEVELOPER_ROOT."field-types/add/");		
	}
	
	Utils::growl("Developer","Created Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/new/$id/");
	