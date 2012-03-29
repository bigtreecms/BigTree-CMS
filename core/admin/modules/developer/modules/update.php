<?
	BigTree::globalizePOSTVars();

	$id = end($path);

	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$admin->updateModule($id,$name,$group,$class,$gbp);	

	$admin->growl("Developer","Updated Module");
	header("Location: ".$developer_root."modules/view/");
	die();	
?>