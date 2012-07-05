<?
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["system"]) {
		$admin->growl("Developer","Access Denied");
		BigTree::redirect($developer_root."settings/view/");
	} else {
		$success = $admin->updateSetting(end($bigtree["path"]),$_POST);
		if ($success) {
			$admin->growl("Developer","Updated Setting");
			BigTree::redirect($developer_root."settings/view/");
		} else {
			$_SESSION["bigtree"]["developer"]["setting_data"] = $_POST;
			$_SESSION["bigtree"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
			BigTree::redirect($developer_root."settings/edit/".end($bigtree["path"])."/");
		}
	}
?>