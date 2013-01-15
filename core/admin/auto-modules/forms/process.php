<?
	$view = BigTreeAutoModule::getRelatedViewForForm($form);
	$data_action = ($_POST["save_and_publish"] || $_POST["save_and_publish_x"] || $_POST["save_and_publish_y"]) ? "publish" : "save";

	// If there's a preprocess function for this module, let's get'r'done.
	$preprocess_changes = array();
	if ($form["preprocess"]) {
		$function = '$preprocess_changes = '.$form["preprocess"].'($_POST);';
		eval($function);
		// Update the $_POST
		if (is_array($preprocess_changes)) {
			foreach ($preprocess_changes as $key => $val) {
				$_POST[$key] = $val;
			}
		}
	}

	// Find out what kind of permissions we're allowed on this item.  We need to check the EXISTING copy of the data AND what it's turning into and find the lowest of the two permissions.
	$permission = $admin->getAccessLevel($module,$_POST,$form["table"]);
	if ($_POST["id"] && $permission && $permission != "n") {
		$original_item = BigTreeAutoModule::getItem($form["table"],$_POST["id"]);
		$existing_item = BigTreeAutoModule::getPendingItem($form["table"],$_POST["id"]);
		$previous_permission = $admin->getAccessLevel($module,$existing_item["item"],$form["table"]);
		$original_permission = $admin->getAccessLevel($module,$original_item["item"],$form["table"]);

		// If the current permission is e or p, drop it down to e if the old one was e.
		if ($previous_permission != "p") {
			$permission = $previous_permission;
		}
		// Check the original. If we're not already at "you're not allowed" then apply the original permission.
		if ($permission != "n" && $original_permission != "p") {
			$permission = $original_permission;
		}
	}

	// If permission check fails, stop and throw the denied page.
	if (!$permission || $permission == "n") {
		$admin->stop(file_get_contents(BigTree::path("admin/auto-modules/forms/_denied.php")));
	}

	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	// Make sure we have permission to this module before update.
	$fields = $form["fields"];
	$crops = array();
	$many_to_many = array();
	$fails = array();

	// Let us figure out what was posted and get the data...!
	$item = array();

	$data = $_POST;
	$file_data = $_FILES;

	foreach ($fields as $key => $options) {
		$type = $options["type"];
		$tpath = BigTree::path("admin/form-field-types/process/$type.php");

		$no_process = false;
		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		if (file_exists($tpath)) {
			include $tpath;
		} else {
			$value = htmlspecialchars($data[$key]);
		}
		if (!BigTreeForms::validate($value,$options["validation"])) {
			$error = $options["error_message"] ? $options["error_message"] : BigTreeForms::errorMessage($value,$options["validation"]);
			$fails[] = array(
				"field" => $options["title"],
				"error" => $error
			);
		}
		$value = $admin->autoIPL($value);
		if (!$no_process) {
			$item[$key] = $value;
		}
	}
	// See if we added anything in pre-processing that wasn't a field in the form.
	if (is_array($preprocess_changes)) {
		foreach ($preprocess_changes as $key => $val) {
			if (!isset($item[$key])) {
				$item[$key] = $val;
			}
		}
	}
	// Sanitize the form data so it fits properly in the database (convert dates to MySQL-friendly format and such)
	$formParser = new BigTreeForms($form["table"]);
	$item = $formParser->sanitizeFormDataForDB($item);
	$tags = $_POST["_tags"];
	$resources = $_POST["_resources"];
	$edit_id = $_POST["id"] ? $_POST["id"] : false;
	$new_id = false;
	$table = $form["table"];
	// Let's stick it in the database or whatever!
	if ($permission == "e" || $data_action == "save") {
		// We're an editor
		if ($edit_id) {
			BigTreeAutoModule::submitChange($module["id"],$table,$edit_id,$item,$many_to_many,$tags);
			$admin->growl($module["name"],"Saved ".$form["title"]." Draft");
		} else {
			$edit_id = "p".BigTreeAutoModule::createPendingItem($module["id"],$table,$item,$many_to_many,$tags);
			$admin->growl($module["name"],"Created ".$form["title"]." Draft");
		}
		if ($edit_id && is_numeric($edit_id)) {
			$published = true;
		} else {
			$published = false;
		}
	} elseif ($permission == "p" && $data_action == "publish") {
		// We're a publisher
		if ($edit_id) {
			if (substr($edit_id,0,1) == "p") {
				$edit_id = BigTreeAutoModule::publishPendingItem($table,substr($edit_id,1),$item,$many_to_many,$tags);
				$admin->growl($module["name"],"Updated & Published ".$form["title"]);
			} else {
				BigTreeAutoModule::updateItem($table,$edit_id,$item,$many_to_many,$tags);
				$admin->growl($module["name"],"Updated ".$form["title"]);
			}
		} else {
			$edit_id = BigTreeAutoModule::createItem($table,$item,$many_to_many,$tags);
			$admin->growl($module["name"],"Created ".$form["title"]);
		}
		$published = true;
	}
	// Kill off any applicable locks to the entry
	if ($edit_id) {
		$admin->unlock($table,$edit_id);
	}
	
	// Figure out if we should return to a view with search results / page / sorting preset.
	if (isset($_POST["_bigtree_return_view_data"])) {
		$return_view_data = unserialize(base64_decode($_POST["_bigtree_return_view_data"]));
		if (!$form["return_view"] || $form["return_view"] == $return_view_data["view"]) {
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
	if ($form["return_view"]) {
		$a = $admin->getModuleActionForView($form["return_view"]);
		if ($a["route"]) {
			$redirect_url = ADMIN_ROOT.$module["route"]."/".$a["route"]."/".$redirect_append;
		} else {
			$redirect_url = ADMIN_ROOT.$module["route"]."/".$redirect_append;
		}
	} elseif ($form["return_url"]) {
		$redirect_url = $form["return_url"].$redirect_append;
	} else {
		$redirect_url = ADMIN_ROOT.$module["route"]."/".$redirect_append;
	}
	if ($_POST["_bigtree_preview"]) {
		$admin->ungrowl();
		$redirect_url = $view["preview_url"].$edit_id."/?bigtree_preview_return=".urlencode($bigtree["form_root"].$edit_id."/");
	}
	// Check to see if this is a positioned element, if it is and the form is selected to move to the top, update the record.
	$table_description = BigTree::describeTable($table);
	if (isset($table_description["columns"]["position"]) && $form["default_position"] == "Top" && !$_POST["id"]) {
		$max = sqlrows(sqlquery("SELECT id FROM `$table`"));
		BigTreeAutoModule::updateItem($table,$edit_id,array("position" => $max));
	}
	// If there's a callback function for this module, let's get'r'done.
	if ($form["callback"]) {
		$function = $form["callback"].'($edit_id,$item,$published);';
		eval($function);
	}

	// Put together saved form information for the error or crop page in case we need it.
	$suffix = $view["suffix"] ? "-".$view["suffix"] : "";
	$edit_link = ADMIN_ROOT.$module["route"]."/edit$suffix/$edit_id/";
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"view" => $view,
		"id" => $edit_id,
		"return_link" => $redirect_url,
		"edit_link" => $edit_link,
		"fails" => $fails,
		"crops" => $crops
	);
	
	if (count($fails)) {
		BigTree::redirect($bigtree["form_root"]."error/");
	} elseif (count($crops)) {
		BigTree::redirect($bigtree["form_root"]."crop/");
	}

	BigTree::redirect($redirect_url);
?>