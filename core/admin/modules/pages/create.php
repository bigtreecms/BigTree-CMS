<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	$r = $admin->getPageAccessLevel($_POST["parent"]); 
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
		die("You do not have access to create a child for this page.");
	}
	
	$resources = array();
	$crops = array();
	$fails = array();
	
	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");
	// Parse callouts
	include BigTree::path("admin/modules/pages/_callout-parse.php");	
	
	if ($publisher && $_POST["ptype"] == "Create & Publish") {
		// Let's make it happen.
		$page = $admin->createPage($_POST);
		$admin->growl("Pages","Created & Published Page");
	} else {
		$page = "p".$admin->createPendingPage($_POST);
		$admin->growl("Pages","Created Page Draft");
	}
	
	if (count($crops)) {
		$retpage = $admin_root."pages/view-tree/".$_POST["parent"]."/";
		include BigTree::path("admin/modules/pages/_crop.php");
	} elseif (count($fails)) {
		include BigTree::path("admin/modules/pages/_failed.php");
	} else {
		header("Location: ".$admin_root."pages/view-tree/".$_POST["parent"]."/");
		die();
	}
?>