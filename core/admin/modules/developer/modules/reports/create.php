<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$module_id = end($bigtree["path"]);

	$report = ModuleReport::create(
		$module_id,
		$_POST["title"],
		$_POST["table"],
		$_POST["type"],
		$_POST["filters"],
		$_POST["fields"],
		$_POST["parser"],
		$_POST["view"]
	);

	$action_route = SQL::unique("bigtree_module_actions", "route", "report", array("module" => $module_id), true);
	ModuleAction::create($module_id, $_POST["title"], $action_route, "on", "export", $report->ID);

	Utils::growl("Developer", "Created Module Report");
	Router::redirect(DEVELOPER_ROOT."modules/edit/$module_id/");
	