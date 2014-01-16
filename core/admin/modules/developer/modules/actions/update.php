<?
	$item = $admin->getModuleAction(end($bigtree["path"]));
	$admin->updateModuleAction(end($bigtree["path"]),$_POST["name"],$_POST["route"],$_POST["in_nav"],$_POST["class"],$_POST["form"],$_POST["view"],$_POST["report"],$_POST["level"],$_POST["position"]);

	$admin->growl("Developer","Updated Action");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$item["module"]."/");
?>