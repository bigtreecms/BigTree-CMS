<?php
	namespace BigTree;

	// Grab View Data
	$view = new ModuleView($_POST["view"]);
	$module = new Module($view->Module);
	$table = $view->Table;
	
	if ($module->UserAccessLevel == "p") {
		parse_str($_POST["sort"],$data);
	
		foreach ($data["row"] as $position => $id) {
			if (is_numeric($id)) {
				SQL::update($table, $id, array("position" => (count($data["row"]) - $position)));
				ModuleView::cacheForAll($id, $table);
			} else {
				\BigTreeAutoModule::updatePendingItemField(substr($id,1),"position",(count($data["row"]) - $position));
				ModuleView::cacheForAll(substr($id, 1), $table, true);
			}
		}
	}

	// Find any view that uses this table for grouping and wipe its view cache
	$dependant = ModuleView::allDependant($table);
	
	foreach ($dependant as $view) {
		$view->clearCache();
	}
