<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}

	$bigtree["layout"] = "front-end";

	$page = $_POST["page"];

	if ($page[0] == "p") {
		$change_id = substr($page,1);
		$f = $admin->getPendingChange($change_id);
		$pdata = $f["changes"];
		$r = $admin->getPageAccessLevel($pdata["parent"]);
	} else {
		$r = $admin->getPageAccessLevel($page);
		// Get pending page data with resources decoded and tags.
		$pdata = $cms->getPendingPage($page,true,true);
	}

	// Work out the permissions
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>You do not have access to this page.</p>
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

	// Save the template since we're not passing in the full update data.
	$_POST["template"] = $pdata["template"];

	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");

	// Un-htmlspecialchar everything since createPage / updatePage is going to re-do it.
	foreach ($pdata as $key => $val) {
		if (!is_array($val)) {
			$pdata[$key] = htmlspecialchars_decode($val);
		}
	}

	$pdata["resources"] = $_POST["resources"];

	if ($publisher && $_POST["ptype"] == "Save & Publish") {
		// Let's make it happen.
		if ($page[0] == "p") {
			// It's a pending page, so let's create one.
			$page = $admin->createPage($pdata);
			$admin->deletePendingChange($change_id);
		} else {
			// It's an existing page.
			$admin->updatePage($page,$pdata);
		}

		$refresh_link = $cms->getLink($page);
	} else {
		if (!$_POST["parent"]) {
			$_POST["parent"] = $pdata["parent"];
		}
		$admin->submitPageChange($page,$pdata);

		$refresh_link = $cms->getPreviewLink($page);
	}

	$admin->unlock("bigtree_pages",$page);

	if (count($bigtree["crops"])) {
		include BigTree::path("admin/modules/pages/_front-end-crop.php");
	} elseif (count($bigtree["errors"])) {
		include BigTree::path("admin/modules/pages/_front-end-error.php");
	} else {
?>
<script>parent.BigTreeBar.refresh("<?=$refresh_link?>");</script>
<?
	}
?>