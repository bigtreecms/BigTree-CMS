<?php
	namespace BigTree;

	/**
	 * @global ModuleReport $report
	 */
	
	if (!count($_POST)) {
		Router::redirect(MODULE_ROOT);
	}

	$view = $report->RelatedModuleView;
	$items = $report->getResults($_POST, $_POST["*sort"]["field"], $_POST["*sort"]["order"]);

	if ($view->Type == "searchable" || $view->Type == "grouped" || $view->Type == "draggable" || $view->Type == "nested") {
		include Router::getIncludePath("admin/auto-modules/reports/views/text.php");
	} else {
		include Router::getIncludePath("admin/auto-modules/reports/views/images.php");
	}
	