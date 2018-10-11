<?php
	$admin->verifyCSRFToken();
	$access_level = $admin->getPageAccessLevel($_POST["parent"]);
	
	if ($access_level != "p" && $access_level != "e") {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>You do not have access to create a child for this page.</p>
	</section>
</div>
<?php
		$admin->stop();
	}

	// Adjust template
	if ($_POST["redirect_lower"]) {
		$_POST["template"] = "!";
		$_POST["external"] = "";
		$_POST["new_window"] = "";
	} elseif ($_POST["external"]) {
		$_POST["template"] = "";
		$_POST["new_window"] = isset($_POST["new_window"]) ? $_POST["new_window"] : "";
	} else {
		$_POST["new_window"] = "";
	}

	$bigtree["crops"] = array();
	$bigtree["errors"] = array();
	// Initiate the Storage class for backwards compat.
	$upload_service = new BigTreeStorage;

	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");

	if ($access_level == "p" && $_POST["ptype"] == "Create & Publish") {
		// Let's make it happen.
		$page = $admin->createPage($_POST);
		$did_publish = true;
		$admin->growl("Pages","Created & Published Page");
	} else {
		$page = "p".$admin->createPendingPage($_POST);
		$did_publish = false;
		$admin->growl("Pages","Created Page Draft");
	}

	// Run any post-processing hook
	if (!empty($bigtree["template"]["hooks"]["post"])) {
		call_user_func($bigtree["template"]["hooks"]["post"], $page, $bigtree["entry"], $did_publish);
	}

	// Track resource allocation
	$admin->allocateResources("bigtree_pages", $page);

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $page,
		"return_link" => ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/",
		"edit_link" => ADMIN_ROOT."pages/edit/$page/",
		"errors" => $bigtree["errors"]
	);

	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = $cms->cacheUnique("org.bigtreecms.crops",$bigtree["crops"]);
		BigTree::redirect(ADMIN_ROOT."pages/crop/$page/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."pages/error/$page/");
	}

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
?>