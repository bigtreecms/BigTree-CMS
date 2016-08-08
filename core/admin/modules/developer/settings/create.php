<?php
	namespace BigTree;
	
	$setting = Setting::create($_POST["id"], $_POST["name"], $_POST["description"], $_POST["type"],
							   json_decode($_POST["options"], true), $_POST["extension"], false,
							   $_POST["encrypted"], $_POST["locked"]);

	if ($setting) {
		Utils::growl("Developer","Created Setting");
		Router::redirect(DEVELOPER_ROOT."settings/");
	} else {
		$_SESSION["bigtree_admin"]["developer"]["setting_data"] = $_POST;
		$_SESSION["bigtree_admin"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
		
		Router::redirect(DEVELOPER_ROOT."settings/add/");
	}
	