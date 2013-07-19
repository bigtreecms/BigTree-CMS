<?
	$admin->updateSettingValue("bigtree-internal-salesforce-api",array());
	$admin->growl("Salesforce API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/salesforce/");
?>