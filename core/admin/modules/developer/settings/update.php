<?
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["system"]) {
		$admin->growl("Developer","Access Denied");
		header("Location: ".$developer_root."settings/view/");
		die();		
	} else {
		$success = $admin->updateSetting(end($bigtree["path"]),$_POST);
		if ($success) {
			$admin->growl("Developer","Updated Setting");
			header("Location: ".$developer_root."settings/view/");
			die();
		} else {
			$_SESSION["bigtree"]["developer"]["setting_data"] = $_POST;
			$_SESSION["bigtree"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
			header("Location: ".$developer_root."settings/edit/".end($bigtree["path"])."/");
			die();
		}
	}
?>