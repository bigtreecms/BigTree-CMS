<?php
	namespace BigTree;
	
	$template = new Template($_POST["id"]);
	$template->update($_POST["name"], $_POST["level"], $_POST["module"], $_POST["resources"]);
	
	Utils::growl("Developer","Updated Template");

	if (isset($_POST["return_to_front"])) {
		Router::redirect(ADMIN_ROOT."pages/edit/".$_POST["return_to_front"]."/");
	} else {
		Router::redirect(DEVELOPER_ROOT."templates/");
	}
	