<?php
	namespace BigTree;
	
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["system"]) {
		Utils::growl("Developer","Access Denied","error");
		Router::redirect(DEVELOPER_ROOT."settings/");
	} else {
		$success = $admin->updateSetting(end($bigtree["path"]),$_POST);
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
			Router::redirect(DEVELOPER_ROOT."settings/edit/".end($bigtree["path"])."/");
		}
	}
	