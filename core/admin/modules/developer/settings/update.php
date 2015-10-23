<?php
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["system"]) {
		$admin->growl("Developer","Access Denied","error");
		BigTree::redirect(DEVELOPER_ROOT."settings/");
	} else {
		$success = $admin->updateSetting(end($bigtree["path"]),$_POST);
		if ($success) {
			$admin->growl("Developer","Updated Setting");
			if ($_POST["return_to_front"]) {
				BigTree::redirect(ADMIN_ROOT."settings/edit/".$_POST["id"]."/");
			} else {
				BigTree::redirect(DEVELOPER_ROOT."settings/");
			}
		} else {
			$_SESSION["bigtree_admin"]["developer"]["setting_data"] = $_POST;
			$_SESSION["bigtree_admin"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
			BigTree::redirect(DEVELOPER_ROOT."settings/edit/".end($bigtree["path"])."/");
		}
	}