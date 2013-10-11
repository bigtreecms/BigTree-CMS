<?
	$bigtree["form"] = $form = BigTreeAutoModule::getForm($bigtree["module_action"]["form"]);
	$bigtree["form_root"] = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";

	// Provide developers a nice handy link for edit/return of this form
	$bigtree["developer_nav_links"][] = array("url" => ADMIN_ROOT."developer/modules/forms/edit/".$bigtree["form"]["id"]."/?return=front","class" => "icon_settings_generic","title" => "Edit in Developer");
	// Audit Trail link
	if ($bigtree["edit_id"]) {
		$bigtree["developer_nav_links"][] = array("url" => ADMIN_ROOT."developer/audit/search/?table=".$bigtree["form"]["table"]."&entry=".$bigtree["edit_id"],"class" => "icon_trail","title" => "Audit Trail");		
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