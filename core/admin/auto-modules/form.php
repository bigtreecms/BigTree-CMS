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
	
	if (!empty($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]) && !is_numeric(substr($bigtree["edit_id"], 1))) {
		Auth::stop();
	}
	
	$form = new ModuleForm($interface->Array);
	$form->Root = ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/";
	
	// In case someone is relying on $bigtree["form"] for backwards compatibility
	$bigtree["form"] = $form->Array;
	$bigtree["related_view"] = $form->RelatedModuleView;	
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
	