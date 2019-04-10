<?php
	
	namespace BigTree;
	
	/**
	 * @global array $bigtree
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
	$bigtree["preprocessed"] = [];
	
	if ($form->Hooks["pre"]) {
		$bigtree["preprocessed"] = call_user_func($form->Hooks["pre"], $_POST);
		
		// Update the $_POST
		if (is_array($bigtree["preprocessed"])) {
			foreach ($bigtree["preprocessed"] as $key => $val) {
				$_POST[$key] = $val;
			}
		}
	}
	
	// Find out what kind of permissions we're allowed on this item.
	// We need to check the EXISTING copy of the data AND what it's turning into and find the lowest of the two permissions.
	$bigtree["access_level"] = Auth::user()->getAccessLevel($module, $_POST, $form->Table);
	
	if ($_POST["id"] && $bigtree["access_level"] && $bigtree["access_level"] != "n") {
		$original_item = $form->getEntry($_POST["id"]);
		$existing_item = $form->getPendingEntry($_POST["id"]);
		
		$previous_permission = Auth::user()->getAccessLevel($module, $existing_item["item"], $form->Table);
		$original_permission = Auth::user()->getAccessLevel($module, $original_item["item"], $form->Table);
		
		// If the current permission is e or p, drop it down to e if the old one was e.
		if ($previous_permission != "p") {
			$bigtree["access_level"] = $previous_permission;
		}
		
		// Check the original. If we're not already at "you're not allowed" then apply the original permission.
		if ($bigtree["access_level"] != "n" && $original_permission != "p") {
			$bigtree["access_level"] = $original_permission;
		}
	}
	
	// If permission check fails, stop and throw the denied page.
	if (!$bigtree["access_level"] || $bigtree["access_level"] == "n") {
		Auth::stop(file_get_contents(Router::getIncludePath("admin/auto-modules/forms/_denied.php")));
	}
	
	$bigtree["crops"] = [];
	$bigtree["many-to-many"] = [];
	$bigtree["errors"] = [];
	$bigtree["entry"] = [];
	$bigtree["post_data"] = $_POST;
	$bigtree["file_data"] = Field::getParsedFilesArray();
	
	foreach ($form->Fields as $resource) {
		$field = new Field([
			"type" => $resource["type"],
			"title" => $resource["title"],
			"key" => $resource["column"],
			"settings" => $resource["settings"],
			"ignore" => false,
			"input" => $bigtree["post_data"][$resource["column"]],
			"file_input" => $bigtree["file_data"][$resource["column"]]
		]);
		
		$output = $field->process();
		
		if (!is_null($output)) {
			$bigtree["entry"][$field->Key] = $output;
		}
	}
	
	// See if we added anything in pre-processing that wasn't a field in the form.
	if (is_array($bigtree["preprocessed"])) {
		foreach ($bigtree["preprocessed"] as $key => $val) {
			if (!isset($bigtree["entry"][$key])) {
				$bigtree["entry"][$key] = $val;
			}
		}
	}
	
	// Sanitize the form data so it fits properly in the database (convert dates to MySQL-friendly format and such)
	$bigtree["entry"] = SQL::prepareData($form->Table, $bigtree["entry"]);
	
	// Make some easier to write out vars for below.
	$tags = $_POST["_tags"] ?: [];
	$edit_id = $_POST["id"] ? $_POST["id"] : false;
	$table = $form->Table;
	$item = $bigtree["entry"];
	$many_to_many = $bigtree["many-to-many"];
	
	// Check to see if this is a positioned element
	// If it is and the form is setup to create new items at the top and this is a new record, update the position column.
	$table_description = SQL::describeTable($table);
	
	if (isset($table_description["columns"]["position"]) && $form->DefaultPosition == "Top" && !$_POST["id"]) {
		$max = (int) SQL::fetchSingle("SELECT COUNT(*) FROM `$table`") +
			   (int) SQL::fetchSingle("SELECT COUNT(*) FROM `bigtree_pending_changes` WHERE `table` = ?", $table);
		$item["position"] = $max;
	}
	
	// Let's stick it in the database or whatever!
	$data_action = ($_POST["save_and_publish"] || $_POST["save_and_publish_x"] || $_POST["save_and_publish_y"]) ? "publish" : "save";
	$did_publish = false;
	
	// We're an editor or "Save" was chosen
	if ($bigtree["access_level"] == "e" || $data_action == "save") {
		$og_changes = OpenGraph::handleData(null, null, $_POST["_open_graph_"], $_FILES["_open_graph_"]["image"]);
		
		// We have an existing module entry we're saving a change to.
		if ($edit_id) {
			$form->createChangeRequest($edit_id, $item, $many_to_many, $tags, $og_changes);
			Utils::growl($module->Name, "Saved ".$form->Title." Draft");
		// It's a new entry, so we create a pending item.
		} else {
			$edit_id = "p".$form->createPendingEntry($item, $many_to_many, $tags, $og_changes);
			Utils::growl($module->Name, "Created ".$form->Title." Draft");
		}
	// We're a publisher and we want to publish
	} elseif ($bigtree["access_level"] == "p" && $data_action == "publish") {
		// If we have an edit_id we're modifying something that exists.
		if ($edit_id) {
			// If the edit id starts with a "p" it's a pending entry we're publishing.
			if (substr($edit_id, 0, 1) == "p") {
				$form->deletePendingEntry(substr($edit_id, 1));
				$edit_id = $form->createEntry($item, $many_to_many, $tags);
				$did_publish = true;
				
				Utils::growl($module->Name, "Updated & Published ".$form->Title);
			// Otherwise we're updating something that is already published
			} else {
				$form->updateEntry($edit_id, $item, $many_to_many, $tags);
				$did_publish = true;
				
				Utils::growl($module->Name, "Updated ".$form->Title);
			}
		// We're creating a new published entry.
		} else {
			$edit_id = $form->createEntry($item, $many_to_many, $tags);
			$did_publish = true;
			
			Utils::growl($module->Name, "Created ".$form->Title);
		}
	}
	
	if ($did_publish && $form->OpenGraphEnabled) {
		OpenGraph::handleData($form->Table, $edit_id, $_POST["_open_graph_"],
							  $_FILES["_open_graph_"]["image"]);
	}
	
	// Catch errors
	if ($edit_id === false && $did_publish) {
		$bigtree["errors"][] = [
			"field" => "SQL Query",
			"error" => SQL::$ErrorLog[count(SQL::$ErrorLog) - 1]
		];
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
			$action = ModuleAction::getByInterface($form->ReturnView);
			
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
		call_user_func($form->Hooks["post"], $edit_id, $item, $did_publish);
	}
	
	// Custom callback for only publishes
	if ($did_publish && $form->Hooks["publish"]) {
		call_user_func($form->Hooks["publish"], $table, $edit_id, $item, $many_to_many, $tags);
	}
	
	// Track resource allocation
	Resource::allocate($module->ID, $edit_id);
	
	// Put together saved form information for the error or crop page in case we need it.
	$edit_action = $module->getEditAction($form->ID);
	$_SESSION["bigtree_admin"]["form_data"] = [
		"view" => $view->ID,
		"id" => $edit_id,
		"return_link" => $redirect_url,
		"edit_link" => ADMIN_ROOT.$module->Route."/".$edit_action->Route."/$edit_id/",
		"errors" => $bigtree["errors"]
	];
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect($form->Root."crop/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect($form->Root."error/");
	} else {
		Router::redirect($redirect_url);
	}
	