<?
	$admin->updateSettingValue("bigtree-internal-flickr-api",array());
	$admin->growl("Flickr API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/flickr/");
?>