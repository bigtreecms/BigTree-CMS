<?php
	// If the last command is numeric then we're editing something.
	if (is_numeric(end($bigtree["commands"])) || is_numeric(substr(end($bigtree["commands"]),1))) {
		$bigtree["edit_id"] = $edit_id = end($bigtree["commands"]);
	// Otherwise we're adding something or we're processing something we were editing.
	} else {
		$bigtree["edit_id"] = $edit_id = $_POST["id"] ? $_POST["id"] : false;
	}

	if (!empty($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]) && !is_numeric(substr($bigtree["edit_id"], 1))) {
		$admin->stop();
	}
	
	$bigtree["form"] = $form = BigTreeAutoModule::getForm($bigtree["module_action"]["form"]);
	$bigtree["form_root"] = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";
	$bigtree["related_view"] = BigTreeAutoModule::getRelatedViewForForm($bigtree["form"]);
	
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
