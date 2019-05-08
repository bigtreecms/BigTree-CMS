<?php
	namespace BigTree;
	
	$report = Router::$ModuleInterface->Module->Reports[Router::$ModuleInterface->ID];
	$action = Router::$Commands[0];

	if ($action == "csv") {
		include Router::getIncludePath("admin/auto-modules/reports/csv.php");
	} elseif ($action == "view") {
		include Router::getIncludePath("admin/auto-modules/reports/view.php");
	} else {
		include Router::getIncludePath("admin/auto-modules/reports/filter.php");
	}
	