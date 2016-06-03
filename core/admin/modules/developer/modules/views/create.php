<?php
	namespace BigTree;
	
	Globalize::POST();

	$options = json_decode($options,true);

	$table_description = SQL::describeTable($table);
	$columns = $table_description["columns"];
	$errors = array();
	
	// Check for errors
	if (($type == "draggable" || $type == "draggable-group" || $options["draggable"]) && !$columns["position"]) {
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
		if (isset($actions)) {
			foreach ($actions as $key => $val) {
				if ($val) {
					$clean_actions[$key] = $val;
				}
			}
		}
		$actions = $clean_actions;

		$module = end($bigtree["path"]);

		// Create the view
		$view_id = $admin->createModuleView($module,$title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url);

		// Check to see if there's a default view for the module. If not our route is going to be blank.
		$route = "";
		$landing_exists = ModuleAction::exists($module,"");
		
		if ($landing_exists) {
			$route = SQL::unique("bigtree_module_actions", "route", $cms->urlify("View $title"), array("module" => $module), true);
		}

		// Create an action for the view
		$admin->createModuleAction($module,"View $title",$route,"on","list",$view_id);

		// If we're not working on a new module, just redirect back to the edit module page
		if (!$_POST["new_module"]) {
			Utils::growl("Developer","Created Module View");
			Router::redirect(DEVELOPER_ROOT."modules/edit/$module/");
		}
?>
<div class="container">
	<section>
		<h3><?=Text::translate("View :title:", false, array(":title:" => $title))?></h3>
		<p><?=Text::translate("Your view has been created. You may continue to create a form for this view or return to the module overview instead.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/edit/<?=$module?>/" class="button white"><?=Text::translate("Return to Module")?></a>
		<a href="<?=DEVELOPER_ROOT?>modules/forms/add/?module=<?=$module?>&table=<?=urlencode($table)?>&title=<?=urlencode($title)?>&view=<?=$view_id?>" class="button blue"><?=Text::translate("Add Form")?></a>
	</footer>
</div>
<?php
	}
?>