<?php
	namespace BigTree;
	
	Globalize::POST();

	$admin->updateTemplate($id,$name,$level,$module,$resources);
	Utils::growl("Developer","Updated Template");

	if (isset($_POST["return_to_front"])) {
		Router::redirect(ADMIN_ROOT."pages/edit/".$_POST["return_to_front"]."/");
	} else {
		Router::redirect(DEVELOPER_ROOT."templates/");
	}
	