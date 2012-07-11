<?
	$recent = $dogwood->getRecentPosts(5);
	$dogwood_title = "Recent Posts";
	
	BigTree::redirect($pageLink . "post/" . $recent[0]["route"] . "/");
?>