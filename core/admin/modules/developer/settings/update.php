<?
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["system"]) {
		$admin->growl("Developer","Access Denied");
		BigTree::redirect($developer_root."settings/");
	} else {
		$success = $admin->updateSetting(end($bigtree["path"]),$_POST);
		if ($success) {
			$admin->growl("Developer","Updated Setting");
			BigTree::redirect($developer_root."settings/");
		} else {
			$_SESSION["bigtree_admin"]["developer"]["setting_data"] = $_POST;
			$_SESSION["bigtree_admin"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
			BigTree::redirect($developer_root."settings/edit/".end($bigtree["path"])."/");
		}
	}
?>