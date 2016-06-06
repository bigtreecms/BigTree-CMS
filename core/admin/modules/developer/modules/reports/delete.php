<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$report = new ModuleReport(end($bigtree["commands"]));
	$report->delete();

	Utils::growl("Developer","Deleted Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$report->Module."/");
	