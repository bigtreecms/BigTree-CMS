<?php
	namespace BigTree;
	
	// If the last command is numeric then we're editing something.
	if (is_numeric(end(Router::$Commands)) || is_numeric(substr(end(Router::$Commands), 1))) {
		$edit_id = end(Router::$Commands);
	// Otherwise we're adding something or we're processing something we were editing.
	} else {
		$edit_id = $_POST["id"] ?: false;
	}
	
	if (!empty($edit_id) && !is_numeric($edit_id) && !is_numeric(substr($edit_id, 1))) {
		Auth::stop();
	}
	
	$form = Router::$ModuleInterface->Module->Forms[Router::$ModuleInterface->ID];
	$form->Root = ADMIN_ROOT.Router::$Module->Route."/".Router::$ModuleAction->Route."/";
	$action = Router::$Commands[0];
	
	if (!$action || is_numeric($action) || is_numeric(substr($action, 1))) {
		if ($edit_id) {
			if (isset($_GET["force"])) {
				Lock::remove($form->Table, $edit_id);
			}
			
			include Router::getIncludePath("admin/auto-modules/forms/edit.php");
		} else {
			include Router::getIncludePath("admin/auto-modules/forms/add.php");
		}
	} else {
		include Router::getIncludePath("admin/auto-modules/forms/$action.php");
	}
	