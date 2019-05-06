<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	// Get some globals for repeated vars
	$table = $_POST["table"];
	$settings = json_decode($_POST["settings"], true);
	$type = $_POST["type"];
	$actions = $_POST["actions"];
	$fields = $_POST["fields"];
	$preview_url = $_POST["preview_url"];
	$exclude_from_search = !empty($_POST["exclude_from_search"]);

	// Check for errors
	$table_description = SQL::describeTable($table);
	$columns = $table_description["columns"];
	$errors = [];

	if (($type == "draggable" || $type == "draggable-group" || $settings["draggable"]) && !$columns["position"]) {
		$errors[] = Text::translate("Sorry, but you can't create a draggable view without a 'position' column in your table.  Please create a position column (integer) in your table and try again.");
	}
	
	if ($actions["archive"] && !(($columns["archived"]["type"] == "char" || $columns["archived"]["type"] == "varchar") && $columns["archived"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'archived' that is char(2) in order to use the archive function.");
	}

	if ($actions["approve"] && !(($columns["approved"]["type"] == "char" || $columns["approved"]["type"] == "varchar") && $columns["approved"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'approved' that is char(2) in order to use the approve function.");
	}
	
	if ($actions["feature"] && !(($columns["featured"]["type"] == "char" || $columns["featured"]["type"] == "varchar") && $columns["featured"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'featured' that is char(2) in order to use the feature function.");
	}
	
	if (count($errors)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Update Failed")?></h3>
		</div>
		<?php foreach ($errors as $error) { ?>
		<p><?=$error?></p>
		<?php } ?>
	</section>
	<footer>
		<a href="javascript: history.back();" class="button white"><?=Text::translate("Back")?></a>
	</footer>
</div>
<?php
	} else {
		// Clean up actions
		$clean_actions = [];
		
		foreach ($actions as $key => $val) {
			if ($val) {
				$clean_actions[$key] = $val;
			}
		}
		
		// If we've switched from searchable -> anything else or vice versa, wipe the width columns.
		// Also wipe them if we have added or removed a column.
		$view = new ModuleView(end(Router::$Path));
		$keys_match = true;

		foreach ($view->Fields as $key => $field) {
			if (empty($fields[$key])) {
				$keys_match = false;
			}
		}
		
		foreach ($fields as $key => $field) {
			if (empty($view->Fields[$key])) {
				$keys_match = false;
			}
		}

		// Check actions
		if (count($view->Actions) != count($actions)) {
			$keys_match = false;
		}
		
		// Check preview field
		if ((!$view->PreviewURL && $preview_url) || ($view->PreviewURL && !$preview_url)) {
			$keys_match = false;
		}
		
		if (!$keys_match || ($view->Type == "searchable" && $type != "searchable") || ($type == "searchable" && $view->Type != "searchable")) {
			foreach ($fields as $key => $field) {
				unset($fields[$key]["width"]);
			}
		}
		
		// Let's update the view and clear its cache
		$view->update(
			$_POST["title"],
			$_POST["description"],
			$table,
			$type,
			$settings,
			$fields,
			$actions,
			$_POST["related_form"] ?: null,
			$preview_url
		);
		$view->clearCache();

		Utils::growl("Developer","Updated View");

		if ($_POST["return_page"]) {
			Router::redirect($_POST["return_page"]);
		} else {
			Router::redirect(DEVELOPER_ROOT."modules/edit/".$view->Module."/");
		}
	}
