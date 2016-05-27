<?php
	namespace BigTree;

	/**
	 * @global ModuleForm $form
	 * @global Module $module
	 */

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
	if ($_POST["_bigtree_hashcash"] != md5($cleaned_string) || $_POST["_bigtree_email"]) {
		$_SESSION["bigtree_admin"]["post_hash_failed"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}

	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	// If there's a preprocess function for this module, let's get'r'done.
	$bigtree["preprocessed"] = array();

	if ($form->Hooks["pre"]) {
		$bigtree["preprocessed"] = call_user_func($form->Hooks["pre"],$_POST);

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
	$bigtree["post_data"] = $_POST;
	$bigtree["file_data"] = Field::getParsedFilesArray();

	foreach ($form->Fields as $resource) {
		$field = new Field(array(
			"type" => $resource["type"],
			"title" => $resource["title"],
			"key" => $resource["column"],
			"options" => $resource["options"],
			"ignore" => false,
			"input" => $bigtree["post_data"][$resource["column"]],
			"file_input" => $bigtree["file_data"][$resource["column"]]
		));

		$output = $field->process();
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
	$bigtree["entry"] = SQL::prepareData($form->Table,$bigtree["entry"]);

	// Make some easier to write out vars for below.
	$tags = $_POST["_tags"];
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

	$did_publish = false;
	if ($form->DefaultPending) {
		$edit_id = "p".$form->createPendingEntry($item, $many_to_many, $tags);
	} else {
		$edit_id = $form->createEntry($item, $many_to_many, $tags);
		$did_publish = true;
	}

	// If there's a callback function for this module, let's get'r'done.
	if ($form->Hooks["post"]) {
		call_user_func($form->Hooks["post"],$edit_id,$item,$did_publish);
	}

	// Publish Hook
	if ($did_publish && $form->Hooks["publish"]) {
		call_user_func($form->Hooks["publish"],$table,$edit_id,$item,$many_to_many,$tags);
	}

	// Track resource allocation
	Resource::allocate($module->ID, $edit_id);

	// Put together saved form information for the error or crop page in case we need it.
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"errors" => $bigtree["errors"]
	);

	// If we have errors, we want to save the data and drop the entry from the database but give them the info again
	if (count($bigtree["errors"])) {
		$item = $form->getEntry($edit_id);
		$_SESSION["bigtree_admin"]["form_data"]["saved"] = $item["item"];

		if ($form->DefaultPending) {
			$form->deletePendingEntry(substr($edit_id, 1));
		} else {
			$form->deleteEntry($edit_id);
		}
	}
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect($form->Root."crop/?id=".$form->ID."&hash=".$form->Hash);
	} elseif (count($bigtree["errors"])) {
		Router::redirect($form->Root."error/?id=".$form->ID."&hash=".$form->Hash);
	} else {
		Router::redirect($form->Root."complete/?id=".$form->ID."&hash=".$form->Hash);
	}