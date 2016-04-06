<?php
	namespace BigTree;
	
	if (!count($_POST)) {
		Router::redirect(MODULE_ROOT);
	}
	
	$items = BigTreeAutoModule::getReportResults($bigtree["report"],$bigtree["view"],$bigtree["form"],$_POST,$_POST["*sort"]["field"],$_POST["*sort"]["order"]);

	if ($bigtree["view"]["type"] == "searchable" || $bigtree["view"]["type"] == "grouped" || $bigtree["view"]["type"] == "draggable" || $bigtree["view"]["type"] == "nested") {
		Router::includeFile("admin/auto-modules/reports/views/text.php");
	} else {
		Router::includeFile("admin/auto-modules/reports/views/images.php");
	}
	