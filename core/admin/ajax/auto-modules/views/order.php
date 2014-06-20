<?
	// Grab View Data
	$view = BigTreeAutoModule::getView($_POST["view"]);
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($view));
	$access_level = $admin->getAccessLevel($module);
	$table = $view["table"];
	
	if ($access_level == "p") {
		parse_str($_POST["sort"]);
	
		foreach ($row as $position => $id) {
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET position = '".(count($row)-$position)."' WHERE id = '".sqlescape($id)."'");
				BigTreeAutoModule::recacheItem($id,$table);
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"position",(count($row)-$position));
				BigTreeAutoModule::recacheItem(substr($id,1),$table,true);
			}
		}
	}

	// Find any view that uses this table for grouping and wipe its view cache
	$dependant = BigTreeAutoModule::getDependantViews($table);
	foreach ($dependant as $v) {
		BigTreeAutoModule::clearCache($v["table"]);
	}
?>