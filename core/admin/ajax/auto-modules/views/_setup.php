<?php
	namespace BigTree;

	header("Content-type: text/javascript");
	
	CSRF::verify();

	$id = SQL::escape($_GET["id"]);
	
	// Grab View Data
	$view = new ModuleView($_GET["view"]);
	$related_form = $view->RelatedForm;
	$table = $view->Table;

	// Get module
	$module = new Module($view->Module);

	// Get the pending item to check permissions
	$form = new ModuleForm(["table" => $table]);
	$pending_entry = $form->getPendingEntry($id);
	$item = $pending_entry["item"];

	// Check permission
	$access_level = Auth::user()->getAccessLevel($module, $item, $table);

	if ($access_level != "n") {
		// Get the original item to check permissions on it as well
		$original_item = $form->getEntry($id);
		$original_access_level = Auth::user()->getAccessLevel($module, $original_item["item"], $table);
		
		if ($original_access_level != "p") {
			$access_level = $original_access_level;
		}
	}
	
	$run_publish_hook = function($changes) use ($id, $related_form, $table) {
		if (empty($related_form->Hooks["publish"])) {
			return;
		}
		
		call_user_func($related_form->Hooks["publish"], $table, $id, $changes, null, null);
	};
	