<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if ($_POST["group_new"]) {
		$group = ModuleGroup::create($_POST["group_new"]);
		$group_id = $group->ID;
	} else {
		$group_id = intval($_POST["group_existing"]);
	}
	
	$module = new Module(end($bigtree["path"]));
	$module->update($_POST["name"], $group_id, $_POST["class"], $_POST["gbp"], $_POST["icon"], $_POST["developer_only"]);

	Utils::growl("Developer","Updated Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
