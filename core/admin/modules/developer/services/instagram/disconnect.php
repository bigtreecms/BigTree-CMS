<?
	$admin->updateSettingValue("bigtree-internal-instagram-api",array());
	$admin->growl("Instagram API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/instagram/");
?>