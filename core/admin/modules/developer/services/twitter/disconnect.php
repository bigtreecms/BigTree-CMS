<?
	$admin->updateSettingValue("bigtree-internal-twitter-api",array());
	$admin->growl("Twitter API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/twitter/");
?>