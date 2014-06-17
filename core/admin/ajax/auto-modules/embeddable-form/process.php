<?
	// Generate a hash of everything posted
	$complete_string = "";
	foreach ($_POST as $key => $val) {
		if ($key != "_bigtree_hashcash") {
			$complete_string .= $val;
		}
	}
	// Stop Robots - See if it matches the passed hash and that _bigtree_email wasn't filled out
	if ($_POST["_bigtree_hashcash"] != md5($complete_string) || $_POST["_bigtree_email"]) {
		$_SESSION["bigtree_admin"]["post_hash_failed"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}

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

	// Backwards compatibility.
	$upload_service = new BigTreeUploadService;
	
	$bigtree["crops"] = array();
	$bigtree["many-to-many"] = array();
	$bigtree["errors"] = array();
	$bigtree["entry"] = array();

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["modules"];

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

	if ($bigtree["form"]["default_pending"]) {
		$edit_id = "p".BigTreeAutoModule::createPendingItem($bigtree["module"]["id"],$table,$item,$many_to_many,$tags);
	} else {
		$edit_id = BigTreeAutoModule::createItem($table,$item,$many_to_many,$tags);
		$did_publish = true;
	}

	// If there's a callback function for this module, let's get'r'done.
	if ($bigtree["form"]["callback"]) {
		call_user_func($bigtree["form"]["callback"],$edit_id,$item,$did_publish);
	}

	// Track resource allocation
	$admin->allocateResources($bigtree["module"]["id"],$edit_id);

	// Put together saved form information for the error or crop page in case we need it.
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"errors" => $bigtree["errors"],
		"crops" => $bigtree["crops"]
	);

	// If we have errors, we want to save the data and drop the entry from the database but give them the info again
	if (count($bigtree["errors"])) {
		$item = BigTreeAutoModule::getItem($table,$edit_id);
		$_SESSION["bigtree_admin"]["form_data"]["saved"] = $item["item"];
		BigTreeAutoModule::deletePendingItem($table,substr($edit_id,1));
	}
	
	if (count($bigtree["crops"])) {
		BigTree::redirect($bigtree["form_root"]."crop/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect($bigtree["form_root"]."error/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	} else {
		BigTree::redirect($bigtree["form_root"]."complete/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	}
?>