<?
	$success = $admin->createSetting($_POST);
	if ($success) {
		$admin->growl("Developer","Created Setting");
		BigTree::redirect($developer_root."settings/");
	} else {
		$_SESSION["bigtree_admin"]["developer"]["setting_data"] = $_POST;
		$_SESSION["bigtree_admin"]["developer"]["error"] = "The ID you specified is already in use by another Setting.";
		BigTree::redirect($developer_root."settings/add/");
	}
?>