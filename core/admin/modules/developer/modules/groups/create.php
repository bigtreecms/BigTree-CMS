<?
	$admin->createModuleGroup($_POST["name"]);
	
	$admin->growl("Developer","Created Module Group");
	BigTree::redirect(DEVELOPER_ROOT."modules/groups/");
?>