<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	/** @var ModuleView $view */
	$view = Router::$ModuleInterface->Module->Views[Router::$ModuleInterface->ID];
	
	// Extension view
	if (strpos($view->Type,"*") !== false) {
		list($extension,$view_type) = explode("*",$view->Type);
		include SERVER_ROOT."extensions/$extension/plugins/view-types/$view_type/draw.php";
	} else {
		include Router::getIncludePath("admin/auto-modules/views/".$view->Type.".php");
	}
