<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}

	$bigtree["layout"] = "front-end";
	$bigtree["crops"] = array();
	$bigtree["errors"] = array();

	$page_id = $_POST["page"];
	$page = Page::getPageDraft($page_id);
	$access_level = $page->UserAccessLevel;
	
	// Work out the permissions
	if ($access_level == "p") {
		$publisher = true;
	} elseif ($access_level == "e") {
		$publisher = false;
	} else {
		Auth::stop("You do not have access to this page.", Router::getIncludePath("admin/layouts/_error.php"));
	}

	$resources = array();

	// Save the template since we're not passing in the full update data.
	$_POST["template"] = $page->Template;
	
	// Parse resources
	include Router::getIncludePath("admin/modules/pages/_resource-parse.php");

	// Handle permissions on trunk
	if (Auth::user()->Level < 2) {
		
		unset($_POST["trunk"]);
	}

	if ($publisher && $_POST["ptype"] == "Save & Publish") {
		// Let's make it happen.
		if ($page_id[0] == "p") {
			// It's a pending page, so let's create one.
			$page_id = $admin->createPage($pdata);
			$admin->deletePendingChange($change_id);
		} else {
			// It's an existing page.
			$admin->updatePage($page_id,$pdata);
		}

		$refresh_link = Link::get($page_id);
	} else {
		if (!$_POST["parent"]) {
			$_POST["parent"] = $pdata["parent"];
		}
		$admin->submitPageChange($page_id,$pdata);

		$refresh_link = Link::getPreview($page_id);
	}

	$admin->unlock("bigtree_pages",$page_id);

	if (count($bigtree["crops"])) {
		include Router::getIncludePath("admin/modules/pages/_front-end-crop.php");
	} elseif (count($bigtree["errors"])) {
		include Router::getIncludePath("admin/modules/pages/_front-end-error.php");
	} else {
?>
<script>parent.BigTreeBar.refresh("<?=$refresh_link?>");</script>
<?php
	}
?>