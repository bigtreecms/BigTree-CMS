<?
	$admin->verifyCSRFToken();
	
	$id = $_GET["id"];
	$page = $cms->getPage($id,false);
	$access = $admin->archivePage($id);
	
	$admin->growl("Pages","Archived Page");

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
?>
