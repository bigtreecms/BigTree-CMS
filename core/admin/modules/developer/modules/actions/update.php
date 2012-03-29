<?
	$item = $admin->getModuleAction(end($path));
	$admin->updateModuleAction(end($path),$_POST["name"],$_POST["route"],$_POST["in_nav"],$_POST["class"]);

	$admin->growl("Developer","Updated Action");
	header("Location: ".$developer_root."modules/edit/".$item["module"]."/");
	die();
?>