<?php
	namespace BigTree;

	/**
	 * @global string $table
	 * @global ModuleView $view
	 */
	
	CSRF::verify();

	// Grab View Data
	$view = new ModuleView($_POST["view"]);
	$module = new Module($view->Module);
	$form = new ModuleForm(array("table" => $view->Table));
	
	if ($module->UserAccessLevel == "p") {
		parse_str($_POST["sort"],$data);
		$count = count($data["row"]);
	
		foreach ($data["row"] as $position => $id) {
			// Live Entry
			if (is_numeric($id)) {
				SQL::update($view->Table, $id, array("position" => ($count - $position)));
				ModuleView::cacheForAll($id, $table);
			// Pending Entry
			} else {
				$form->updatePendingEntryField(substr($id, 1), "position", ($count - $position));
				ModuleView::cacheForAll(substr($id, 1), $view->Table, true);
			}
		}
	}

	// Find any view that uses this table for grouping and wipe its view cache
	$dependant = ModuleView::allDependant($view->Table);
	
	foreach ($dependant as $view) {
		$view->clearCache();
	}
