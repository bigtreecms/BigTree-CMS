<?
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	
	$fields = $view["fields"];
	foreach ($fields as $key => $field) {
		$fields[$key]["width"] = 0;
	}
		
	// Update the view
	$admin->updateModuleViewFields(end($bigtree["path"]),$fields);

	$action = $admin->getModuleActionForView(end($bigtree["path"]));

	$admin->growl("Developer","Reset View Styles");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
?>