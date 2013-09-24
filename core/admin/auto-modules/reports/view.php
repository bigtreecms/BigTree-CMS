<?
	if (!count($_POST)) {
		BigTree::redirect(MODULE_ROOT);
	}
	
	$items = BigTreeAutoModule::getReportResults($bigtree["report"],$bigtree["view"],$bigtree["form"],$_POST,$_POST["*sort"]["field"],$_POST["*sort"]["order"]);

	if ($bigtree["view"]["type"] == "searchable" || $bigtree["view"]["type"] == "grouped" || $bigtree["view"]["type"] == "draggable" || $bigtree["view"]["type"] == "nested") {
		include BigTree::path("admin/auto-modules/reports/views/text.php");
	} else {
		include BigTree::path("admin/auto-modules/reports/views/images.php");
	}
?>