<?
	// Generate a hash of everything posted
	$complete_string = "";
	$hash_recurse = function($array) {
		global $complete_string,$hash_recurse;
		foreach ($array as $key => $val) {
			if ($key !== "_bigtree_hashcash") {
				if (is_array($val)) {
					$hash_recurse($val);
				} else {
					$complete_string .= $val;
				}
			}
		}
	};
	$hash_recurse($_POST);

	// Clean out carriage return and line feed characters since JS and PHP seem to disagree on their presence
	$cleaned_string = "";
	for ($i = 0; $i < strlen($complete_string); $i++) {
		$char = substr($complete_string,$i,1);
		$code = ord($char);
		if ($code != 10 && $code != 13) {
			$cleaned_string .= $char;
		}
	}

	// Stop Robots - See if it matches the passed hash and that _bigtree_email wasn't filled out
	if (trim($_POST["_bigtree_hashcash"]) != trim(md5($cleaned_string)) || $_POST["_bigtree_email"]) {
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
	if ($bigtree["form"]["hooks"]["pre"]) {
		$bigtree["preprocessed"] = call_user_func($bigtree["form"]["hooks"]["pre"],$_POST);
		// Update the $_POST
		if (is_array($bigtree["preprocessed"])) {
			foreach ($bigtree["preprocessed"] as $key => $val) {
				$_POST[$key] = $val;
			}
		}
	}

	$bigtree["crops"] = array();
	$bigtree["many-to-many"] = array();
	$bigtree["errors"] = array();
	$bigtree["entry"] = array();

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["modules"];

	$bigtree["post_data"] = $_POST;
	$bigtree["file_data"] = BigTree::parsedFilesArray();

	foreach ($bigtree["form"]["fields"] as $resource) {
		$field = array(
			"type" => $resource["type"],
			"title" => $resource["title"],
			"key" => $resource["column"],
			"options" => $resource["options"],
			"ignore" => false,
			"input" => $bigtree["post_data"][$resource["column"]],
			"file_input" => $bigtree["file_data"][$resource["column"]]
		);

		$output = BigTreeAdmin::processField($field);
		if (!is_null($output)) {
			$bigtree["entry"][$field["key"]] = $output;
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
		$edit_id = "p".BigTreeAutoModule::createPendingItem($bigtree["form"]["module"],$table,$item,$many_to_many,$tags,$bigtree["form"]["hooks"]["publish"],true);
	} else {
		$edit_id = BigTreeAutoModule::createItem($table,$item,$many_to_many,$tags);
		$did_publish = true;
	}

	// If there's a callback function for this module, let's get'r'done.
	if ($bigtree["form"]["hooks"]["post"]) {
		call_user_func($bigtree["form"]["hooks"]["post"],$edit_id,$item,$did_publish);
	}

	// Publish Hook
	if ($did_publish && $bigtree["form"]["hooks"]["publish"]) {
		call_user_func($bigtree["form"]["hooks"]["publish"],$table,$edit_id,$item,$many_to_many,$tags);
	}

	// Track resource allocation
	$admin->allocateResources($bigtree["module"]["id"],$edit_id);

	// Put together saved form information for the error or crop page in case we need it.
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"errors" => $bigtree["errors"]
	);

	// If we have errors, we want to save the data and drop the entry from the database but give them the info again
	if (count($bigtree["errors"])) {
		$item = BigTreeAutoModule::getItem($table,$edit_id);
		$_SESSION["bigtree_admin"]["form_data"]["saved"] = $item["item"];
		if ($bigtree["form"]["default_pending"]) {
			BigTreeAutoModule::deletePendingItem($table,substr($edit_id,1));
		} else {
			BigTreeAutoModule::deleteItem($table,$edit_id);
		}
	}
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = $cms->cacheUnique("org.bigtreecms.crops",$bigtree["crops"]);
		BigTree::redirect($bigtree["form_root"]."crop/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect($bigtree["form_root"]."error/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	} else {
		BigTree::redirect($bigtree["form_root"]."complete/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	}
?>
