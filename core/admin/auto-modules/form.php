<?php
	
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global ModuleInterface $interface
	 */
	
	// If the last command is numeric then we're editing something.
	if (is_numeric(end($bigtree["commands"])) || is_numeric(substr(end($bigtree["commands"]), 1))) {
		$bigtree["edit_id"] = $edit_id = end($bigtree["commands"]);
		// Otherwise we're adding something or we're processing something we were editing.
	} else {
		$bigtree["edit_id"] = $edit_id = $_POST["id"] ? $_POST["id"] : false;
	}
	
	$form = new ModuleForm($interface->Array);
	$form->Root = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";
	
	// In case someone is relying on $bigtree["form"] for backwards compatibility
	$bigtree["form"] = $form->Array;
	
	// Provide developers a nice handy link for edit/return of this form
	if (Auth::user()->Level > 1) {
		$bigtree["subnav_extras"][] = [
			"link" => ADMIN_ROOT."developer/modules/forms/edit/".$form->ID."/?return=front",
			"icon" => "setup",
			"title" => "Edit in Developer"
		];
		
		// Audit Trail link
		if ($bigtree["edit_id"]) {
			$bigtree["subnav_extras"][] = [
				"link" => ADMIN_ROOT."developer/audit/search/?table=".$form->Table."&entry=".$bigtree["edit_id"]."&".CSRF::$Field."=".urlencode(CSRF::$Token),
				"icon" => "trail",
				"title" => "View Audit Trail"
			];
		}
	}
	
	$action = $bigtree["commands"][0];
	
	if (!$action || is_numeric($action) || is_numeric(substr($action, 1))) {
		if ($bigtree["edit_id"]) {
			if (isset($_GET["force"])) {
				Lock::remove($form->Table, $bigtree["edit_id"]);
			}
			
			include Router::getIncludePath("admin/auto-modules/forms/edit.php");
		} else {
			include Router::getIncludePath("admin/auto-modules/forms/add.php");
		}
	} else {
		include Router::getIncludePath("admin/auto-modules/forms/$action.php");
	}
	