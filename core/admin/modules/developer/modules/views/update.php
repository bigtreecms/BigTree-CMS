<?	
	BigTree::globalizePOSTVars();
	
	$table_description = BigTree::describeTable($table);
	$columns = $table_description["columns"];
	
	$options = json_decode($options, true);
	
	$errors = array();
	// Check for errors
	if (($type == "draggable" || $type == "draggable-group" || $options["draggable"]) && !$columns["position"]) {
		$errors[] = "Sorry, but you can't create a draggable view without a 'position' column in your table.  Please create a position column (integer) in your table and try again.";
	}
	
	if ($actions["archive"] && !(($columns["archived"]["type"] == "char" || $columns["archived"]["type"] == "varchar") && $columns["archived"]["size"] == "2")) {
		$errors[] = "Sorry, but you must have a column named 'archived' that is char(2) in order to use the archive function.";
	}
	if ($actions["approve"] && !(($columns["approved"]["type"] == "char" || $columns["approved"]["type"] == "varchar") && $columns["approved"]["size"] == "2")) {
		$errors[] = "Sorry, but you must have a column named 'approved' that is char(2) in order to use the approve function.";
	}
	if ($actions["feature"] && !(($columns["featured"]["type"] == "char" || $columns["featured"]["type"] == "varchar") && $columns["featured"]["size"] == "2")) {
		$errors[] = "Sorry, but you must have a column named 'featured' that is char(2) in order to use the feature function.";
	}
	
	if (count($errors)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Update Failed</h3>
		</div>
		<? foreach ($errors as $error) { ?>
		<p><?=$error?></p>
		<? } ?>
	</section>
	<footer>
		<a href="javascript: history.back();" class="button white">Back</a>
	</footer>
</div>
<?
	} else {
		// Clean up actions
		$clean_actions = array();
		foreach ($actions as $key => $val) {
			if ($val) {
				$clean_actions[$key] = $val;
			}
		}
		$actions = $clean_actions;
		
		// If we've switched from searchable -> anything else or vice versa, wipe the width columns.
		// Also wipe them if we have added or removed a column.
		$old_view = BigTreeAutoModule::getView(end($bigtree["path"]));
		$keys_match = true;
		foreach ($old_view["fields"] as $key => $field) {
			if (!$fields[$key]) {
				$keys_match = false;
			}
		}
		
		foreach ($fields as $key => $field) {
			if (!$old_view["fields"][$key]) {
				$keys_match = false;
			}
		}

		// Check actions
		if (count($old_view["actions"]) != count($actions)) {
			$keys_match = false;
		}
		
		// Check preview field
		if ((!$old_view["preview_url"] && $preview_url) || ($old_view["preview_url"] && !$preview_url)) {
			$keys_match = false;
		}
		
		if (!$keys_match || ($old_view["type"] == "searchable" && $type != "searchable") || ($type == "searchable" && $old_view["type"] != "searchable")) {
			foreach ($fields as $key => $field) {
				unset($fields[$key]["width"]);
			}
		}
		
		// Let's update the view and clear its cache
		$admin->updateModuleView(end($bigtree["path"]),$title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url);
		BigTreeAutoModule::clearCache(end($bigtree["path"]));
		
		$action = $admin->getModuleActionForView(end($bigtree["path"]));
		$admin->growl("Developer","Updated View");

		if ($_POST["return_page"]) {
			BigTree::redirect($_POST["return_page"]);
		} else {
			BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$action["module"]."/");
		}
	}
?>