<?
	$admin->createModuleGroup($_POST["name"], $_POST["in_nav"]);
	
	$admin->growl("Developer","Created Module Group");
	header("Location: ".$developer_root."modules/groups/view/");
	die();
?>