<?
	header("Content-type: text/javascript");
	
	$id = sqlescape($_GET["id"]);
	// Grab View Data
	$view = BigTreeAutoModule::getView($_GET["view"]);
	$table = $view["table"];
	// Get module
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($_GET["view"]));
	// Get the item
	$item = BigTreeAutoModule::getPendingItem($table,$id);
	$item = $item["item"];
	// Check permission
	$perm = $admin->getAccessLevel($module,$item,$table);
?>