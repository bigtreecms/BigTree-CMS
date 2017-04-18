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
		$_POST["hooks"],
		$_POST["default_position"],
		$_POST["return_view"],
		$_POST["return_url"],
		$_POST["tagging"]
	);

	Utils::growl("Developer","Updated Module Form");

	if ($_POST["return_page"]) {
		Router::redirect($_POST["return_page"]);
	} else {
		Router::redirect(DEVELOPER_ROOT."modules/edit/".$form->Module."/");
	}
	