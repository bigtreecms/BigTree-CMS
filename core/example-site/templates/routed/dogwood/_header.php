<?
	// Get blog settings
	$settings = $cms->getSetting("btx-dogwood-settings");
	$dogwood = new BTXDogwood;
	$blog_link = $cms->getLink($page["id"]);
	
	$bigtree["layout"] = "blog";
?>