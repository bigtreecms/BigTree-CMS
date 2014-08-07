<?
	$bigtree["report"] = BigTreeAutoModule::getReport($bigtree["module_action"]["report"]);
	$bigtree["form"] = BigTreeAutoModule::getRelatedFormForReport($bigtree["report"]);
	$bigtree["view"] = $bigtree["report"]["view"] ? BigTreeAutoModule::getView($bigtree["report"]["view"]) : BigTreeAutoModule::getRelatedViewForReport($bigtree["report"]);
	if ($admin->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/modules/reports/edit/".$bigtree["report"]["id"]."/?return=front","icon" => "setup","title" => "Edit in Developer");
	}
	
	$action = $bigtree["commands"][0];
	if ($action == "csv") {
		include BigTree::path("admin/auto-modules/reports/csv.php");
	} elseif ($action == "view") {
		include BigTree::path("admin/auto-modules/reports/view.php");
	} else {
		include BigTree::path("admin/auto-modules/reports/filter.php");
	}
?>