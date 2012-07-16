<?
	// Get blog settings
	$settings = $cms->getSetting("btx-dogwood-settings");
	// Setup root link for pages (this is equivalent to $cms->getLink($bigtree["page"]["id"]) but saves a SQL call.
	$blog_link = WWW_ROOT.$bigtree["page"]["path"]."/";	
	// Instantiate the Dogwood Blog class.
	$dogwood = new BTXDogwood;
	// Load all the pages in this module into the blog layout.
	$bigtree["layout"] = "blog";
	// By default we're going to say it's not a detail page.
	$post_detail = false;
?>