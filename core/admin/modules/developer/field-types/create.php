<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$id = $_POST["id"];
	
	if (FieldType::exists($id) ||
		file_exists("../core/admin/form-field-types/draw/$id.php") ||
		file_exists("../core/admin/form-field-types/process/$id.php")) {
		
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		
		Router::redirect(DEVELOPER_ROOT."field-types/add/");
	} elseif (!FieldType::create($_POST["id"], $_POST["name"], $_POST["use_cases"], $_POST["self_draw"] ? true : false)) {
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		
		Router::redirect(DEVELOPER_ROOT."field-types/add/");
	}
	
	Admin::growl("Developer", "Created Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/new/$id/");
	