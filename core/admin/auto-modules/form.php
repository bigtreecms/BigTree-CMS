<?
	$bigtree["form"] = $form = BigTreeAutoModule::getForm($bigtree["module_action"]["form"]);
	$bigtree["form_root"] = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";
	
	$action = $bigtree["commands"][0];

	if (!$action || is_numeric($action) || is_numeric(substr($action,1))) {
		if ($bigtree["edit_id"]) {
			if (isset($_GET["force"])) {
				$admin->unlock($bigtree["form"]["table"],$bigtree["edit_id"]);
			}
			include BigTree::path("admin/auto-modules/forms/edit.php");
		} else {
			include BigTree::path("admin/auto-modules/forms/add.php");
		}
	} else {
		include BigTree::path("admin/auto-modules/forms/$action.php");
	}
?>