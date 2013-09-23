<?
	BigTree::globalizePOSTVars();
	$admin->updateModuleReport(end($bigtree["commands"]),$title,$table,$type,$filters,$fields,$parser,$view);
	$admin->growl("Developer","Updated Module Report");
	$action = $admin->getModuleActionForReport(end($bigtree["commands"]));

	if ($_POST["return_page"]) {
		BigTree::redirect($_POST["return_page"]);
	} else {
		BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
	}
?>