<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$setting = new Setting(end(Router::$Path));
	
	if ($setting->System) {
		Utils::growl("Developer","System Settings Are Not Editable","error");
		Router::redirect(DEVELOPER_ROOT."settings/");
	} else {
		$success = $setting->update($_POST["id"], $_POST["type"], json_decode($_POST["settings"], true), $_POST["name"],
									$_POST["description"], $_POST["locked"], $_POST["encrypted"], false);
		
		if ($success) {
			Utils::growl("Developer","Updated Setting");
			
			if ($_POST["return_to_front"]) {
				Router::redirect(ADMIN_ROOT."settings/edit/".$_POST["id"]."/");
			} else {
				Router::redirect(DEVELOPER_ROOT."settings/");
			}
		} else {
			$_SESSION["bigtree_admin"]["developer"]["setting_data"] = $_POST;
			$_SESSION["bigtree_admin"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
			
			Router::redirect(DEVELOPER_ROOT."settings/edit/".end(Router::$Path)."/");
		}
	}
	