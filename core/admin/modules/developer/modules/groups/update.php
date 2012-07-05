<?
	$admin->updateModuleGroup(end($bigtree["path"]),$_POST["name"],$_POST["in_nav"]);	

	$admin->growl("Developer","Updated Module Group");
	BigTree::redirect($developer_root."modules/groups/view/");
?>