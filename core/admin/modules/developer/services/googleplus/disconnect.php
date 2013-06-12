<?
	$admin->updateSettingValue("bigtree-internal-googleplus-api",array());
	$admin->growl("Google+ API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/googleplus/");
?>