<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	// If there's a preprocess function for this module, let's get'r'done.
	$bigtree["preprocessed"] = array();
	if ($bigtree["form"]["preprocess"]) {
		$bigtree["preprocessed"] = call_user_func($bigtree["form"]["preprocess"],$_POST);
		// Update the $_POST
		if (is_array($bigtree["preprocessed"])) {
			foreach ($bigtree["preprocessed"] as $key => $val) {
				$_POST[$key] = $val;
			}
		}
	}

	// Find out what kind of permissions we're allowed on this item.  We need to check the EXISTING copy of the data AND what it's turning into and find the lowest of the two permissions.
	$bigtree["access_level"] = $admin->getAccessLevel($bigtree["module"],$_POST,$bigtree["form"]["table"]);
	if ($_POST["id"] && $bigtree["access_level"] && $bigtree["access_level"] != "n") {
		$original_item = BigTreeAutoModule::getItem($bigtree["form"]["table"],$_POST["id"]);
		$existing_item = BigTreeAutoModule::getPendingItem($bigtree["form"]["table"],$_POST["id"]);
		$previous_permission = $admin->getAccessLevel($bigtree["module"],$existing_item["item"],$bigtree["form"]["table"]);
		$original_permission = $admin->getAccessLevel($bigtree["module"],$original_item["item"],$bigtree["form"]["table"]);

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
		$admin->stop(file_get_contents(BigTree::path("admin/auto-modules/forms/_denied.php")));
	}

	// Backwards compatibility.
	$upload_service = new BigTreeUploadService;
	
	$bigtree["crops"] = array();
	$bigtree["many-to-many"] = array();
	$bigtree["errors"] = array();
	$bigtree["entry"] = array();

	// Some backwards compatibility vars thrown in.
	$bigtree["post_data"] = $data = $_POST;
	$bigtree["file_data"] = BigTree::parsedFilesArray();
	$file_data = $_FILES;

	foreach ($bigtree["form"]["fields"] as $key => $resource) {
		unset($value); // Backwards compat.
		$field = array();
		$field["key"] = $key;
		$field["options"] = $options = $resource;
		$field["ignore"] = false;
		$field["input"] = $bigtree["post_data"][$key];
		$field["file_input"] = $bigtree["file_data"][$key];

		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		$field_type_path = BigTree::path("admin/form-field-types/process/".$resource["type"].".php");
		if (file_exists($field_type_path)) {
			include $field_type_path;
		} else {
			if (is_array($bigtree["post_data"][$field["key"]])) {
				$field["output"] = $bigtree["post_data"][$field["key"]];
			} else {
				$field["output"] = BigTree::safeEncode($bigtree["post_data"][$field["key"]]);
			}
		}

		// Backwards compatibility with older custom field types
		if (!isset($field["output"]) && isset($value)) {
			$field["output"] = $value;
		}
		
		if (!BigTreeAutoModule::validate($field["output"],$field["options"]["validation"])) {
			$error = $field["options"]["error_message"] ? $field["options"]["error_message"] : BigTreeAutoModule::validationErrorMessage($field["output"],$field["options"]["validation"]);
			$bigtree["errors"][] = array(
				"field" => $field["options"]["title"],
				"error" => $error
			);
		}

		if (!$field["ignore"]) {
			// Translate internal link information to relative links.
			if (is_array($field["output"])) {
				$field["output"] = BigTree::translateArray($field["output"]);
			} else {
				$field["output"] = $admin->autoIPL($field["output"]);
			}
			$bigtree["entry"][$field["key"]] = $field["output"];
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
	$bigtree["entry"] = BigTreeAutoModule::sanitizeData($bigtree["form"]["table"],$bigtree["entry"]);

	// Make some easier to write out vars for below.
	$tags = $_POST["_tags"];
	$edit_id = $_POST["id"] ? $_POST["id"] : false;
	$new_id = false;
	$table = $bigtree["form"]["table"];
	$item = $bigtree["entry"];
	$many_to_many = $bigtree["many-to-many"];

	// Check to see if this is a positioned element
	// If it is and the form is setup to create new items at the top and this is a new record, update the position column.
	$table_description = BigTree::describeTable($table);
	if (isset($table_description["columns"]["position"]) && $bigtree["form"]["default_position"] == "Top" && !$_POST["id"]) {
		$max = sqlrows(sqlquery("SELECT id FROM `$table`")) + sqlrows(sqlquery("SELECT id FROM `bigtree_pending_changes` WHERE `table` = '".sqlescape($table)."'"));
		$item["position"] = $max;
	}

	// Let's stick it in the database or whatever!
	$data_action = ($_POST["save_and_publish"] || $_POST["save_and_publish_x"] || $_POST["save_and_publish_y"]) ? "publish" : "save";
	$did_publish = false;
	// We're an editor or "Save" was chosen
	if ($bigtree["access_level"] == "e" || $data_action == "save") {
		// We have an existing module entry we're saving a change to.
		if ($edit_id) {
			BigTreeAutoModule::submitChange($bigtree["module"]["id"],$table,$edit_id,$item,$many_to_many,$tags);
			$admin->growl($bigtree["module"]["name"],"Saved ".$bigtree["form"]["title"]." Draft");
		// It's a new entry, so we create a pending item.
		} else {
			$edit_id = "p".BigTreeAutoModule::createPendingItem($bigtree["module"]["id"],$table,$item,$many_to_many,$tags);
			$admin->growl($bigtree["module"]["name"],"Created ".$bigtree["form"]["title"]." Draft");
		}
	// We're a publisher and we want to publish
	} elseif ($bigtree["access_level"] == "p" && $data_action == "publish") {
		// If we have an edit_id we're modifying something that exists.
		if ($edit_id) {
			// If the edit id starts with a "p" it's a pending entry we're publishing.
			if (substr($edit_id,0,1) == "p") {
				$edit_id = BigTreeAutoModule::publishPendingItem($table,substr($edit_id,1),$item,$many_to_many,$tags);
				$admin->growl($bigtree["module"]["name"],"Updated & Published ".$bigtree["form"]["title"]);
				$did_publish = true;
			// Otherwise we're updating something that is already published
			} else {
				BigTreeAutoModule::updateItem($table,$edit_id,$item,$many_to_many,$tags);
				$admin->growl($bigtree["module"]["name"],"Updated ".$bigtree["form"]["title"]);
				$did_publish = true;
			}
		// We're creating a new published entry.
		} else {
			$edit_id = BigTreeAutoModule::createItem($table,$item,$many_to_many,$tags);
			$admin->growl($bigtree["module"]["name"],"Created ".$bigtree["form"]["title"]);
			$did_publish = true;
		}
	}

	// Kill off any applicable locks to the entry
	if ($edit_id) {
		$admin->unlock($table,$edit_id);
	}
	
	// Figure out if we should return to a view with search results / page / sorting preset.
	if (isset($_POST["_bigtree_return_view_data"])) {
		$return_view_data = unserialize(base64_decode($_POST["_bigtree_return_view_data"]));
		if (!$bigtree["form"]["return_view"] || $bigtree["form"]["return_view"] == $return_view_data["view"]) {
			$redirect_append = array();
			unset($return_view_data["view"]); // We don't need the view passed back.
			foreach ($return_view_data as $key => $val) {
				$redirect_append[] = "$key=".urlencode($val);
			}
			$redirect_append = "?".implode("&",$redirect_append);
		}
	} else {
		$redirect_append = "";
	}
	
	// Get the redirect location.
	$view = BigTreeAutoModule::getRelatedViewForForm($bigtree["form"]);
	// If we specify a specific return view, get that information
	if ($bigtree["form"]["return_view"]) {
		$view = BigTreeAutoModule::getView($bigtree["form"]["return_view"]);
		$action = $admin->getModuleActionForView($bigtree["form"]["return_view"]);
		if ($action["route"]) {
			$redirect_url = ADMIN_ROOT.$bigtree["module"]["route"]."/".$action["route"]."/".$redirect_append;
		} else {
			$redirect_url = ADMIN_ROOT.$bigtree["module"]["route"]."/".$redirect_append;
		}
	// If we specify a specific return URL...
	} elseif ($bigtree["form"]["return_url"]) {
		$redirect_url = $bigtree["form"]["return_url"].$redirect_append;
	// Otherwise just go back to the main module landing.
	} else {
		$redirect_url = ADMIN_ROOT.$bigtree["module"]["route"]."/".$redirect_append;
	}
	// If we've specified a preview URL in our module and the user clicked Save & Preview, return to preview page.
	if ($_POST["_bigtree_preview"]) {
		$admin->ungrowl();
		$redirect_url = $view["preview_url"].$edit_id."/?bigtree_preview_return=".urlencode($bigtree["form_root"].$edit_id."/");
	}

	// If there's a callback function for this module, let's get'r'done.
	if ($bigtree["form"]["callback"]) {
		call_user_func($bigtree["form"]["callback"],$edit_id,$item,$did_publish);
	}

	// Track resource allocation
	$admin->allocateResources($bigtree["module"]["id"],$edit_id);

	// Put together saved form information for the error or crop page in case we need it.
	$edit_action = BigTreeAutoModule::getEditAction($bigtree["module"]["id"],$bigtree["form"]["id"]);
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"view" => $view,
		"id" => $edit_id,
		"return_link" => $redirect_url,
		"edit_link" => ADMIN_ROOT.$bigtree["module"]["route"]."/".$edit_action["route"]."/$edit_id/",
		"errors" => $bigtree["errors"],
		"crops" => $bigtree["crops"]
	);
	
	if (count($bigtree["crops"])) {
		BigTree::redirect($bigtree["form_root"]."crop/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect($bigtree["form_root"]."error/");
	} else {
		BigTree::redirect($redirect_url);
	}
?>