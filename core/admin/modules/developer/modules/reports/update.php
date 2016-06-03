<?php
	namespace BigTree;
	
	Globalize::POST();
	$admin->updateModuleReport(end($bigtree["commands"]),$title,$table,$type,$filters,$fields,$parser,$view);
	Utils::growl("Developer","Updated Module Report");
	$action = $admin->getModuleActionForInterface(end($bigtree["commands"]));

	if ($_POST["return_page"]) {
		Router::redirect($_POST["return_page"]);
	} else {
		Router::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
	}
	