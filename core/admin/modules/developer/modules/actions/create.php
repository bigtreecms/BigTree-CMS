<?
	$admin->createModuleAction(end($path),$_POST["name"],$_POST["route"],$_POST["in_nav"],$_POST["class"]);
	
	$admin->growl("Developer","Created Action");
	header("Location: ".$developer_root."modules/edit/".end($path)."/");
	die();
?>