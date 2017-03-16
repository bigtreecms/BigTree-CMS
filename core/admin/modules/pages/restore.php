<?
	$admin->verifyCSRFToken();
	
	$id = $_GET["id"];
	$page = $cms->getPage($id,false);
	$access = $admin->unarchivePage($id);

	$admin->growl("Pages","Restored Page");
	
	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
?>
