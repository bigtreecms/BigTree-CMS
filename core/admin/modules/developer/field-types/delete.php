<?
	$admin->verifyCSRFToken();
	$admin->deleteFieldType($_GET["id"]);
	
	$admin->growl("Developer","Deleted Field Type");
	BigTree::redirect(DEVELOPER_ROOT."field-types/");
?>