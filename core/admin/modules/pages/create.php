<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
		
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
	$bigtree["crops"] = array();
	$bigtree["errors"] = array();
	// Initiate the Storage class for backwards compat.
	$upload_service = new BigTreeStorage;
	
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
		"errors" => $bigtree["errors"],
		"crops" => $bigtree["crops"]
	);
	
	if (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."pages/error/$page/");
	} elseif (count($bigtree["crops"])) {
		BigTree::redirect(ADMIN_ROOT."pages/crop/$page/");
	}

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
?>