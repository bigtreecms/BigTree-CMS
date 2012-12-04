<?
	$admin->updateSettingValue("bigtree-internal-google-analytics",array());
	$admin->updateSettingValue("bigtree-internal-google-analytics-cache","");
	
	sqlquery("UPDATE bigtree_pages SET ga_page_views = NULL");
	$admin->growl("Analytics","Disconnected");
	
	BigTree::redirect($mroot);
?>