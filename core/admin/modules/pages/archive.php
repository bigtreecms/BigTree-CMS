<?
	$id = end($path);
	$page = $cms->getPage($id,false);
	$access = $admin->archivePage($id);
	
	$admin->growl("Pages","Archived Page");

	header("Location: ".$admin_root."pages/view-tree/".$page["parent"]."/");
	die();
?>
