<?
	$admin->updateModuleGroup(end($path),$_POST["name"],$_POST["in_nav"]);	

	$admin->growl("Developer","Updated Module Group");
	header("Location: ".$developer_root."modules/groups/view/");
	die();
?>