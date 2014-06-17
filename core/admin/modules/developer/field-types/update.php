<?
	$admin->updateFieldType($_POST["id"],$_POST["name"],$_POST["use_cases"],$_POST["self_draw"]);
	
	$admin->growl("Developer","Updated Field Type");
	BigTree::redirect(DEVELOPER_ROOT."field-types/");
?>