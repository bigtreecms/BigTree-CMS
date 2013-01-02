<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;
	
	$access_level = $admin->getPageAccessLevel($_POST["parent"]); 
	if ($access_level != "p" && $access_level != "e") {
?>
<div class="container">
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

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $page,
		"return_link" => ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/",
		"edit_link" => ADMIN_ROOT."pages/edit/$page/",
		"fails" => $fails,
		"crops" => $crops
	);
	
	if (count($fails)) {
		BigTree::redirect(ADMIN_ROOT."pages/error/$page/");
	} elseif (count($crops)) {
		BigTree::redirect(ADMIN_ROOT."pages/crop/$page/");
	}

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
?>