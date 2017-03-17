<?
	$admin->verifyCSRFToken();

	$view = BigTreeAutoModule::getView($_GET["id"]);
	
	$fields = $view["fields"];
	foreach ($fields as $key => $field) {
		$fields[$key]["width"] = 0;
	}
		
	// Update the view
	$admin->updateModuleViewFields($_GET["id"], $fields);

	$action = $admin->getModuleActionForView($_GET["id"]);

	$admin->growl("Developer","Reset View Styles");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
?>