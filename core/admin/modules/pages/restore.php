<?
	$id = end($bigtree["path"]);
	$page = $cms->getPage($id,false);
	$access = $admin->unarchivePage($id);

	$admin->growl("Pages","Restored Page");
	
	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
?>
