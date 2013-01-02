<?
	$admin->updateModuleGroup(end($bigtree["path"]),$_POST["name"]);	

	$admin->growl("Developer","Updated Module Group");
	BigTree::redirect($developer_root."modules/groups/");
?>