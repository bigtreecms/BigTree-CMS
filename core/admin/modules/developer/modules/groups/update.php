<?
	$admin->updateModuleGroup(end($bigtree["path"]),$_POST["name"]);	

	$admin->growl("Developer","Updated Module Group");
	BigTree::redirect(DEVELOPER_ROOT."modules/groups/");
?>