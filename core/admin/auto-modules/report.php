<?php
	namespace BigTree;
	
	/**
	 * @global \BigTreeAdmin $admin
	 * @global array $bigtree
	 * @global ModuleInterface $interface
	 */
	
	$report = new ModuleReport($interface->Array);

	if (Auth::user()->Level > 1) {
		$bigtree["subnav_extras"][] = array(
			"link" => ADMIN_ROOT."developer/modules/reports/edit/".$report->ID."/?return=front",
			"icon" => "setup",
			"title" => "Edit in Developer"
		);
	}
	
	$action = $bigtree["commands"][0];

	if ($action == "csv") {
		include Router::getIncludePath("admin/auto-modules/reports/csv.php");
	} elseif ($action == "view") {
		include Router::getIncludePath("admin/auto-modules/reports/view.php");
	} else {
		include Router::getIncludePath("admin/auto-modules/reports/filter.php");
	}