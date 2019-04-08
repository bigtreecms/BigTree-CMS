<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	// Easier global vars for type checks
	$type = $_POST["type"];
	$settings = json_decode($_POST["settings"], true);
	$actions = $_POST["actions"];
	$module_id = end($bigtree["commands"]);
	$title = $_POST["title"];
	$table = $_POST["table"];

	$table_description = SQL::describeTable($table);
	$columns = $table_description["columns"];
	$errors = array();
	
	// Check for errors
	if (($type == "draggable" || $type == "draggable-group" || $settings["draggable"]) && !$columns["position"]) {
		$errors[] = Text::translate("Sorry, but you can't create a draggable view without a 'position' column in your table.  Please create a position column (integer) in your table and try again.");
	}
	if (isset($actions["archive"]) && !(($columns["archived"]["type"] == "char" || $columns["archived"]["type"] == "varchar") && $columns["archived"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'archived' that is char(2) in order to use the archive function.");
	}
	if (isset($actions["approve"]) && !(($columns["approved"]["type"] == "char" || $columns["approved"]["type"] == "varchar") && $columns["approved"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'approved' that is char(2) in order to use the approve function.");
	}
	if (isset($actions["feature"]) && !(($columns["featured"]["type"] == "char" || $columns["featured"]["type"] == "varchar") && $columns["featured"]["size"] == "2")) {
		$errors[] = Text::translate("Sorry, but you must have a column named 'featured' that is char(2) in order to use the feature function.");
	}

	if (count($errors)) {
		$_SESSION["bigtree_admin"]["developer"]["saved_view"] = $_POST;
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Creation Failed")?></h3>
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
		$clean_actions = array();
		foreach (array_filter((array) $actions) as $key => $val) {
			if ($val) {
				$clean_actions[$key] = $val;
			}
		}

		// Create the view
		$view = ModuleView::create(
			$module_id,
			$title,
			$_POST["description"],
			$table,
			$type,
			$settings,
			$_POST["fields"],
			$clean_actions,
			$_POST["related_form"] ? intval($_POST["related_form"]) : null,
			$_POST["preview_url"]
		);

		// Check to see if there's a default view for the module. If not our route is going to be blank.
		$route = "";
		$landing_exists = ModuleAction::existsForRoute($module_id, "");
		
		if ($landing_exists) {
			$route = SQL::unique("bigtree_module_actions", "route", Link::urlify("View $title"), array("module" => $module_id), true);
		}

		// Create an action for the view
		ModuleAction::create($module_id, "View $title", $route, "on", "list", $view->ID);

		// If we're not working on a new module, just redirect back to the edit module page
		if (!$_POST["new_module"]) {
			Utils::growl("Developer","Created Module View");
			Router::redirect(DEVELOPER_ROOT."modules/edit/$module_id/");
		}
?>
<div class="container">
	<section>
		<h3><?=Text::translate("View :title:", false, array(":title:" => htmlspecialchars($title)))?></h3>
		<p><?=Text::translate("Your view has been created. You may continue to create a form for this view or return to the module overview instead.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/edit/<?=$module_id?>/" class="button white"><?=Text::translate("Return to Module")?></a>
		<a href="<?=DEVELOPER_ROOT?>modules/forms/add/?module=<?=$module_id?>&table=<?=urlencode($table)?>&title=<?=urlencode($title)?>&view=<?=$view->ID?>" class="button blue"><?=Text::translate("Add Form")?></a>
	</footer>
</div>
<?php
	}
?>