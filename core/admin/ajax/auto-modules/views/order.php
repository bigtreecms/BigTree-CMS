<?
	// Grab View Data
	$view = BigTreeAutoModule::getView($_GET["view"]);
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($_GET["view"]));
	$perm = $admin->getAccessLevel($module);
	$table = $view["table"];
	
	if ($perm == "p") {
		parse_str($_GET["sort"]);
	
		foreach ($row as $position => $id) {
			sqlquery("UPDATE `$table` SET position = '".(count($row)-$position)."' WHERE id = '".mysql_real_escape_string($id)."'");
			BigTreeAutoModule::recacheItem($id,$table);
		}
	}
?>