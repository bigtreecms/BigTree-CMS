<?
	$id = $_POST["id"];
	
	if (file_exists("../core/admin/form-field-types/draw/$id.php") || file_exists("../core/admin/form-field-types/process/$id.php")) {
		$_SESSION["bigtree"]["admin_error"] = "The ID you have chosen is reserved for a core field type.";
		$_SESSION["bigtree"]["admin_saved"] = $_POST;
		header("Location: ../add/");
		die();
	}
	
	if ($admin->getFieldType($id)) {
		$_SESSION["bigtree"]["admin_error"] = "The ID you have chosen is already used by a custom field type.";
		$_SESSION["bigtree"]["admin_saved"] = $_POST;
		header("Location: ../add/");
		die();
	}
	
	$admin->createFieldType($_POST["id"],$_POST["name"],$_POST["pages"],$_POST["modules"],$_POST["callouots"]);
	
	$admin->growl("Developer","Created Custom Field Type");
	header("Location: ../new/$id/");
?>