<?php
	namespace BigTree;
	
	Globalize::POST();

	$id = end($bigtree["path"]);

	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$admin->updateModule($id,$name,$group,$class,$gbp,$icon,$developer_only);	

	Utils::growl("Developer","Updated Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
