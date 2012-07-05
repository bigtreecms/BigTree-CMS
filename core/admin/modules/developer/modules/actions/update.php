<?
	$item = $admin->getModuleAction(end($bigtree["path"]));
	$admin->updateModuleAction(end($bigtree["path"]),$_POST["name"],$_POST["route"],$_POST["in_nav"],$_POST["class"],$_POST["level"]);

	$admin->growl("Developer","Updated Action");
	BigTree::redirect($developer_root."modules/edit/".$item["module"]."/");
?>