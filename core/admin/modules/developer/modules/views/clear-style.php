<?php
	namespace BigTree;
	
	$view = \BigTreeAutoModule::getView(end($bigtree["path"]));
	
	$fields = $view["fields"];
	foreach ($fields as $key => $field) {
		$fields[$key]["width"] = 0;
	}
		
	// Update the view
	$admin->updateModuleViewFields(end($bigtree["path"]),$fields);

	$action = $admin->getModuleActionForInterface(end($bigtree["path"]));

	Utils::growl("Developer","Reset View Styles");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
	