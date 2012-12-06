<?
	$form = BigTreeAutoModule::getForm($bigtree["module_action"]["form"]);
	
	$action = $bigtree["commands"][0];

	if (!$action || is_numeric($action)) {
		if ($edit_id) {
			if (isset($_GET["force"])) {
				$admin->unlock($form["table"],$edit_id);
			}
			include BigTree::path("admin/auto-modules/forms/edit.php");
		} else {
			include BigTree::path("admin/auto-modules/forms/add.php");
		}
	} else {
		include BigTree::path("admin/auto-modules/forms/$action.php");
	}
?>