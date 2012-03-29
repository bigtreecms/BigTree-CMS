<?
	$view = BigTreeAutoModule::getView(end($path));
	
	$fields = $view["fields"];
	foreach ($fields as $key => $field) {
		$fields[$key]["width"] = 0;
	}
		
	// Update the view
	$admin->updateModuleViewFields(end($path),$fields);

	$action = $admin->getModuleActionForView(end($path));

	$admin->growl("Developer","Reset View Styles");
	header("Location: ".$developer_root."modules/edit/".$action["module"]."/");
	die();
?>