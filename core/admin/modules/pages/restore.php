<?
	$id = end($path);
	$page = $cms->getPage($id,false);
	$access = $admin->unarchivePage($id);

	$admin->growl("Pages","Restored Page");
	
	header("Location: ".$admin_root."pages/view-tree/".$page["parent"]."/");
	die();
?>
