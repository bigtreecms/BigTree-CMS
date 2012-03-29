<?
	$admin->updatePageParent($_POST["page"],$_POST["parent"]);
	$admin->growl("Pages","Moved Page");
	header("Location: ".$admin_root."pages/view-tree/".$_POST["parent"]."/");
	die();	
?>