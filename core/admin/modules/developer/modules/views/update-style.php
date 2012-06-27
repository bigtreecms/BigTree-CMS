<?
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	
	$fields = $view["fields"];
	$x = 0;
	foreach ($fields as $key => $field) {
		$fields[$key]["width"] = $_POST[$key];
	}
		
	// Update the view
	$admin->updateModuleViewFields(end($bigtree["path"]),$fields);

	$action = $admin->getModuleActionForView(end($bigtree["path"]));

	$admin->growl("Developer","Updated View Styles");
	header("Location: ".$developer_root."modules/edit/".$action["module"]."/");
	die();
?>