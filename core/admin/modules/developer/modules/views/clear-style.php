<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$view = new ModuleView(end($bigtree["path"]));

	foreach ($view->Fields as $key => $field) {
		$view->Fields[$key]["width"] = 0;
	}

	// Save and update column status
	$view->refreshNumericColumns();

	Utils::growl("Developer","Reset View Styles");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$view->Module."/");
	