<?
	$id = end($bigtree["path"]);
	$page = $cms->getPage($id,false);
	$access = $admin->archivePage($id);
	
	$admin->growl("Pages","Archived Page");

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
?>
