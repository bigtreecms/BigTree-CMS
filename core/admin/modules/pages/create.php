<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	$access_level = $admin->getPageAccessLevel($_POST["parent"]); 
	if ($access_level != "p" && $access_level != "e") {
?>
<div class="form_container">
	<section>
		<h3>Error</h3>
		<p>You do not have access to create a child for this page.</p>
	</section>
</div>
<?
		$admin->stop();
	}
	
	$resources = array();
	$crops = array();
	$fails = array();
	
	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");
	// Parse callouts
	include BigTree::path("admin/modules/pages/_callout-parse.php");	
	
	if ($access_level == "p" && $_POST["ptype"] == "Create & Publish") {
		// Let's make it happen.
		$page = $admin->createPage($_POST);
		$admin->growl("Pages","Created & Published Page");
	} else {
		$page = "p".$admin->createPendingPage($_POST);
		$admin->growl("Pages","Created Page Draft");
	}
	
	if (count($crops)) {
		$return_page = ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/";
		include BigTree::path("admin/modules/pages/_crop.php");
	} elseif (count($fails)) {
		include BigTree::path("admin/modules/pages/_failed.php");
	} else {
		BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	}
?>