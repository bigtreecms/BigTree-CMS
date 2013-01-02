<?
	$admin->createModuleGroup($_POST["name"]);
	
	$admin->growl("Developer","Created Module Group");
	BigTree::redirect($developer_root."modules/groups/");
?>