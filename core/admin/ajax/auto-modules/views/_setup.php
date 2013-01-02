<?
	header("Content-type: text/javascript");
	
	$id = sqlescape($_GET["id"]);
	// Grab View Data
	$view = BigTreeAutoModule::getView($_GET["view"]);
	$table = $view["table"];
	// Get module
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($_GET["view"]));
	// Get the item
	$current_item = BigTreeAutoModule::getPendingItem($table,$id);
	$item = $current_item["item"];
	// Check permission
	$access_level = $admin->getAccessLevel($module,$item,$table);
	if ($access_level != "n") {
		$original_item = BigTreeAutoModule::getItem($table,$id);
		$original_access_level = $admin->getAccessLevel($module,$original_item["item"],$table);
		if ($original_access_level != "p") {
			$access_level = $original_access_level;
		}
	}
?>