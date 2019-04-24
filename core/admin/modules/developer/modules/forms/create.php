<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$module = end($bigtree["path"]);
	$title = $_POST["title"];

	$form = ModuleForm::create(
		$module,
		$title,
		$_POST["table"],
		$_POST["fields"],
		json_decode($_POST["hooks"], true),
		$_POST["default_position"],
		$_POST["return_view"],
		$_POST["return_url"],
		!empty($_POST["tagging"]),
		!empty($_POST["open_graph"])
	);

	// See if add/edit actions already exist
	$add_route = "add";
	$edit_route = "edit";
	$translated_add_title = Text::translate("Add :title:", false, [":title:" => $title]);
	$translated_edit_title = Text::translate("Edit :title:", false, [":title:" => $title]);

	// If we already have add/edit routes, get unique new ones for this form
	if (ModuleAction::existsForRoute($module, "add") || ModuleAction::existsForRoute($module, "edit")) {
		$add_route = Link::urlify($translated_add_title);
		$edit_route = Link::urlify($translated_edit_title);
	}

	// Create actions for the form
	ModuleAction::create($module, $translated_add_title, $add_route, "on", "add", $form->ID);
	ModuleAction::create($module, $translated_edit_title, $edit_route, "", "edit", $form->ID);

	Utils::growl("Developer", "Created Module Form");
	Router::redirect(DEVELOPER_ROOT."modules/edit/$module/");
	