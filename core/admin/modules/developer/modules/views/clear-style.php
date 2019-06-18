<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$view = new ModuleView(end(Router::$Path));

	foreach ($view->Fields as $key => $field) {
		$view->Fields[$key]["width"] = 0;
	}

	// Save and update column status
	$view->refreshNumericColumns();

	Admin::growl("Developer","Reset View Styles");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$view->Module."/");
	