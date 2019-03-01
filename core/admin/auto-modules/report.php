<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global ModuleInterface $interface
	 */
	
	$report = new ModuleReport($interface->Array);	
	$action = $bigtree["commands"][0];

	if ($action == "csv") {
		include Router::getIncludePath("admin/auto-modules/reports/csv.php");
	} elseif ($action == "view") {
		include Router::getIncludePath("admin/auto-modules/reports/view.php");
	} else {
		include Router::getIncludePath("admin/auto-modules/reports/filter.php");
	}