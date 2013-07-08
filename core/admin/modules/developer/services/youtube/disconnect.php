<?
	$admin->updateSettingValue("bigtree-internal-youtube-api",array());
	$admin->growl("YouTube API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/youtube/");
?>