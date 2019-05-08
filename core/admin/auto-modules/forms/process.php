<?php
	
	namespace BigTree;
	
	/**
	 * @global ModuleForm $form
	 * @global Module $module
	 */
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	CSRF::verify();
	
	// If there's a preprocess function for this module, run it.
	$preprocessed_data = [];
	
	if ($form->Hooks["pre"]) {
		$preprocessed_data = call_user_func($form->Hooks["pre"], $_POST);
		
		// Update the $_POST
		if (is_array($preprocessed_data)) {
			foreach ($preprocessed_data as $key => $val) {
				$_POST[$key] = $val;
			}
		}
	}
	
	// Find out what kind of permissions we're allowed on this item.
	// We need to check the EXISTING copy of the data AND what it's turning into and find the lowest of the two permissions.
	$access_level = Auth::user()->getAccessLevel($module, $_POST, $form->Table);
	
	if ($_POST["id"] && $access_level && $access_level != "n") {
		$original_item = $form->getEntry($_POST["id"]);
		$existing_item = $form->getPendingEntry($_POST["id"]);
		
		$previous_permission = Auth::user()->getAccessLevel($module, $existing_item["item"], $form->Table);
		$original_permission = Auth::user()->getAccessLevel($module, $original_item["item"], $form->Table);
		
		// If the current permission is e or p, drop it down to e if the old one was e.
		if ($previous_permission != "p") {
			$access_level = $previous_permission;
		}
		
		// Check the original. If we're not already at "you're not allowed" then apply the original permission.
		if ($access_level != "n" && $original_permission != "p") {
			$access_level = $original_permission;
		}
	}
	
	// If permission check fails, stop and throw the denied page.
	if (!$access_level || $access_level == "n") {
		Auth::stop(file_get_contents(Router::getIncludePath("admin/auto-modules/forms/_denied.php")));
	}
	
	$content = [];
	$file_data = Field::getParsedFilesArray();
	
	$form->Fields = Extension::runHooks("fields", "form", $form->Fields, [
		"form" => $form,
		"step" => "process",
		"post_data" => $_POST,
		"file_data" => $file_data
	]);
	
	foreach ($form->Fields as $field) {
		$field = new Field([
			"type" => $field["type"],
			"title" => $field["title"],
			"key" => $field["column"],
			"settings" => $field["settings"],
			"ignore" => false,
			"input" => $_POST[$field["column"]],
			"file_input" => $file_data[$field["column"]]
		]);
		
		$output = $field->process();
		
		if (!is_null($output)) {
			$content[$field->Key] = $output;
		}
	}
	
	// See if we added anything in pre-processing that wasn't a field in the form.
	if (is_array($preprocessed_data)) {
		foreach ($preprocessed_data as $key => $val) {
			if (!isset($content[$key])) {
				$content[$key] = $val;
			}
		}
	}
	
	// Sanitize the form data so it fits properly in the database (convert dates to MySQL-friendly format and such)
	$content = SQL::prepareData($form->Table, $content);
	
	// Make some easier to write out vars for below.
	$tags = $_POST["_tags"] ?: [];
	$edit_id = $_POST["id"] ? $_POST["id"] : false;
	$change_allocation_id = null;
	$table = $form->Table;
	$many_to_many = Field::$ManyToMany;
	
	// Check to see if this is a positioned element
	// If it is and the form is setup to create new items at the top and this is a new record, update the position column.
	$table_description = SQL::describeTable($table);
	
	if (isset($table_description["columns"]["position"]) && $form->DefaultPosition == "Top" && !$_POST["id"]) {
		$max = (int) SQL::fetchSingle("SELECT COUNT(*) FROM `$table`") +
			   (int) SQL::fetchSingle("SELECT COUNT(*) FROM `bigtree_pending_changes` WHERE `table` = ?", $table);
		$content["position"] = $max;
	}
	
	// Let's stick it in the database or whatever!
	$data_action = ($_POST["save_and_publish"] || $_POST["save_and_publish_x"] || $_POST["save_and_publish_y"]) ? "publish" : "save";
	$did_publish = false;
	
	// We're an editor or "Save" was chosen
	if ($access_level == "e" || $data_action == "save") {
		$og_changes = OpenGraph::handleData(null, null, $_POST["_open_graph_"], $_FILES["_open_graph_"]["image"]);
		
		// We have an existing module entry we're saving a change to.
		if ($edit_id) {
			$change_allocation_id = $form->createChangeRequest($edit_id, $content, $many_to_many, $tags, $og_changes);
			Utils::growl($module->Name, "Saved ".$form->Title." Draft");
			Resource::allocate($form->Table, "p".$change_allocation_id);
		// It's a new entry, so we create a pending item.
		} else {
			$edit_id = "p".$form->createPendingEntry($content, $many_to_many, $tags, $og_changes);
			Utils::growl($module->Name, "Created ".$form->Title." Draft");
			Resource::allocate($form->Table, $edit_id);
		}
	// We're a publisher and we want to publish
	} elseif ($access_level == "p" && $data_action == "publish") {
		// If we have an edit_id we're modifying something that exists.
		if ($edit_id) {
			// If the edit id starts with a "p" it's a pending entry we're publishing.
			if (substr($edit_id, 0, 1) == "p") {
				$pending_id = substr($edit_id, 1);
				$form->deletePendingEntry($pending_id);
				$edit_id = $form->createEntry($content, $many_to_many, $tags);
				$did_publish = true;
				
				Resource::updatePendingAllocation($pending_id, $form->Table, $edit_id);
				Utils::growl($module->Name, "Updated & Published ".$form->Title);
			// Otherwise we're updating something that is already published
			} else {
				$pending_change_id = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes
													   WHERE `table` = ? AND `item_id` = ?",
													  $form->Table, $edit_id);
				
				if ($pending_change_id) {
					Resource::deallocate($form->Table, "p".$pending_change_id);
				}
				
				$form->updateEntry($edit_id, $content, $many_to_many, $tags);
				$did_publish = true;
				
				Resource::allocate($form->Table, $edit_id);
				Utils::growl($module->Name, "Updated ".$form->Title);
			}
		// We're creating a new published entry.
		} else {
			$edit_id = $form->createEntry($content, $many_to_many, $tags);
			$did_publish = true;
			
			Resource::allocate($form->Table, $edit_id);
			Utils::growl($module->Name, "Created ".$form->Title);
		}
	}
	
	if ($did_publish && $form->OpenGraphEnabled) {
		OpenGraph::handleData($form->Table, $edit_id, $_POST["_open_graph_"], $_FILES["_open_graph_"]["image"]);
	}
	
	// Catch errors
	if ($edit_id === false && $did_publish) {
		Router::logUserError(SQL::$ErrorLog[count(SQL::$ErrorLog) - 1], "SQL Error");
	}
	
	// Kill off any applicable locks to the entry
	if ($edit_id) {
		Lock::remove($table, $edit_id);
	}
	
	// Figure out if we should return to a view with search results / page / sorting preset.
	$redirect_append = "";
	
	if (isset($_POST["_bigtree_return_view_data"])) {
		$return_view_data = json_decode(base64_decode($_POST["_bigtree_return_view_data"]), true);
		
		if (!$form->ReturnView || $form->ReturnView == $return_view_data["view"]) {
			$redirect_append = [];
			unset($return_view_data["view"]); // We don't need the view passed back.
			
			foreach ($return_view_data as $key => $val) {
				$redirect_append[] = "$key=".urlencode($val);
			}
			
			$redirect_append = "?".implode("&", $redirect_append);
		}
	}
	
	// Get related form to pass to the error page or cropper
	$view = $form->RelatedModuleView;
	
	// If we've specified a preview URL in our module and the user clicked Save & Preview, return to preview page.
	if ($_POST["_bigtree_preview"]) {
		$redirect_url = rtrim($view->PreviewURL, "/")."/".$edit_id."/?bigtree_preview_return=".urlencode($form->Root.$edit_id."/");
		
		Utils::ungrowl();
	} else {
		// If we specify a specific return view, get that information
		if ($form->ReturnView) {
			$action = ModuleAction::getByInterface($form->Module, $form->ReturnView);
			
			if ($action->Route) {
				$redirect_url = ADMIN_ROOT.$module->Route."/".$action->Route."/".$redirect_append;
			} else {
				$redirect_url = ADMIN_ROOT.$module->Route."/".$redirect_append;
			}
		// If we specify a specific return URL...
		} elseif ($form->ReturnURL) {
			$redirect_url = $form->ReturnURL.$redirect_append;
		// Otherwise just go back to the main module landing.
		} else {
			$redirect_url = ADMIN_ROOT.$module->Route."/".$redirect_append;
		}
	}
	
	// If there's a callback function for this module, let's get'r'done.
	if ($form->Hooks["post"]) {
		call_user_func($form->Hooks["post"], $edit_id, $content, $did_publish);
	}
	
	// Custom callback for only publishes
	if ($did_publish && $form->Hooks["publish"]) {
		call_user_func($form->Hooks["publish"], $table, $edit_id, $content, $many_to_many, $tags);
	}
	
	// Track resource allocation
	Resource::allocate($form->Table, $edit_id);
	
	// Put together saved form information for the error or crop page in case we need it.
	$edit_action = $module->getEditAction($form->ID);
	$_SESSION["bigtree_admin"]["form_data"] = [
		"view" => $view->ID,
		"id" => $edit_id,
		"return_link" => $redirect_url,
		"edit_link" => ADMIN_ROOT.$module->Route."/".$edit_action->Route."/$edit_id/",
		"errors" => Router::$UserErrors
	];
	
	if (count(Image::$Crops)) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", Image::$Crops);
		Router::redirect($form->Root."crop/");
	} elseif (count(Router::$UserErrors)) {
		Router::redirect($form->Root."error/");
	} else {
		Router::redirect($redirect_url);
	}
	