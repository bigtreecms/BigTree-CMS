<?
	$bigtree["report"] = BigTreeAutoModule::getReport($bigtree["module_action"]["report"]);
	$bigtree["form"] = BigTreeAutoModule::getRelatedFormForReport($bigtree["report"]);
	$bigtree["view"] = $bigtree["report"]["view"] ? BigTreeAutoModule::getView($bigtree["report"]["view"]) : BigTreeAutoModule::getRelatedViewForReport($bigtree["report"]);
	$bigtree["developer_nav_links"][] = array("url" => ADMIN_ROOT."developer/modules/reports/edit/".$bigtree["report"]["id"]."/?return=front","class" => "icon_settings","title" => "Edit in Developer");
	
	$action = $bigtree["commands"][0];
	if ($action == "csv") {
		include BigTree::path("admin/auto-modules/reports/csv.php");
	} elseif ($action == "view") {
		include BigTree::path("admin/auto-modules/reports/view.php");
	} else {
		include BigTree::path("admin/auto-modules/reports/filter.php");
	}
?>