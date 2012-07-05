<?
	
	$admin->updateSettingValue("bigtree-internal-google-analytics","");
	$admin->updateSettingValue("bigtree-internal-google-analytics-cache","");
	
	sqlquery("UPDATE bigtree_pages SET ga_page_views = NULL WHERE 1");
	
	BigTree::redirect($mroot);
	
?>