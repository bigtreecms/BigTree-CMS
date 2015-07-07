<?
	$page = end($bigtree["path"]);
	
	$page_data = $cms->getPendingPage($page);
	$admin->deletePage($page);
	
	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page_data["parent"]."/");
?>
