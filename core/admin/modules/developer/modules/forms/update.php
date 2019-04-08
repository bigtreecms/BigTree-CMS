<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$form = new ModuleForm(end($bigtree["path"]));
	$form->update(
		$_POST["title"],
		$_POST["table"],
		$_POST["fields"],
		json_decode($_POST["hooks"], true),
		$_POST["default_position"],
		$_POST["return_view"],
		$_POST["return_url"],
		!empty($_POST["tagging"]),
		!empty($_POST["open_graph"])
	);

	Utils::growl("Developer","Updated Module Form");

	if ($_POST["return_page"]) {
		Router::redirect($_POST["return_page"]);
	} else {
		Router::redirect(DEVELOPER_ROOT."modules/edit/".$form->Module."/");
	}
	