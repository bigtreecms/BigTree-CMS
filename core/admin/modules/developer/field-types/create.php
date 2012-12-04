<?
	$id = $_POST["id"];
	
	if (file_exists("../core/admin/form-field-types/draw/$id.php") || file_exists("../core/admin/form-field-types/process/$id.php")) {
		$_SESSION["bigtree_admin"]["admin_error"] = "The ID you have chosen is reserved for a core field type.";
		$_SESSION["bigtree_admin"]["admin_saved"] = $_POST;
		BigTree::redirect($developer_root."field-types/add/");
	}
	
	if ($admin->getFieldType($id)) {
		$_SESSION["bigtree_admin"]["admin_error"] = "The ID you have chosen is already used by a custom field type.";
		$_SESSION["bigtree_admin"]["admin_saved"] = $_POST;
		BigTree::redirect($developer_root."field-types/add/");
	}
	
	$admin->createFieldType($_POST["id"],$_POST["name"],$_POST["pages"],$_POST["modules"],$_POST["callouts"],$_POST["settings"]);
	
	$admin->growl("Developer","Created Field Type");
	BigTree::redirect($developer_root."field-types/new/$id/");
?>