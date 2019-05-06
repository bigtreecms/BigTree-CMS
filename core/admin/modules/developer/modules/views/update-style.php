<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$view = new ModuleView(end(Router::$Path));

	foreach ($view->Fields as $key => $field) {
		$view->Fields[$key]["width"] = $_POST[$key];
	}

	$view->save();

	Utils::growl("Developer","Updated View Styles");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$view->Module."/");
	