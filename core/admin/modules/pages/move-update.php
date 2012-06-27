<?
	if ($_POST["page"] != "0") {
		$admin->updatePageParent($_POST["page"],$_POST["parent"]);
		$admin->growl("Pages","Moved Page");
	}
	header("Location: ".ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	die();	
?>