<?
	// If the last command is numeric then we're editing something.
	if (is_numeric(end($bigtree["commands"])) || is_numeric(substr(end($bigtree["commands"]),1))) {
		$bigtree["edit_id"] = $edit_id = end($bigtree["commands"]);
	// Otherwise we're adding something or we're processing something we were editing.
	} else {
		$bigtree["edit_id"] = $edit_id = $_POST["id"] ? $_POST["id"] : false;
	}
	$bigtree["form"] = $form = BigTreeAutoModule::getForm($bigtree["module_action"]["form"]);
	$bigtree["form_root"] = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";
	
	// Provide developers a nice handy link for edit/return of this form
	if ($admin->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/modules/forms/edit/".$bigtree["form"]["id"]."/?return=front","icon" => "setup","title" => "Edit in Developer");
	}

	// Audit Trail link
	if ($bigtree["edit_id"]) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/audit/search/?table=".$bigtree["form"]["table"]."&entry=".$bigtree["edit_id"],"icon" => "trail","title" => "View Audit Trail");		
	}
	
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