<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$report = new ModuleReport(end(Router::$Commands));
	$report->update(
		$_POST["title"],
		$_POST["table"],
		$_POST["type"],
		$_POST["filters"],
		$_POST["fields"],
		$_POST["parser"],
		$_POST["view"]
	);

	Admin::growl("Developer","Updated Module Report");

	if ($_POST["return_page"]) {
		Router::redirect($_POST["return_page"]);
	} else {
		Router::redirect(DEVELOPER_ROOT."modules/edit/".$report->Module."/");
	}
	