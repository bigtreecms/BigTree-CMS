<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;

	$page = end($path);
	
	if ($page[0] == "p") {
		$change_id = substr($page,1);
		$f = $admin->getPendingChange($change_id);
		$pdata = $f["changes"];
		$r = $admin->getPageAccessLevel($pdata["parent"]);
	} else {
		$r = $admin->getPageAccessLevel($page);
		$pdata = $admin->getPendingPage($page);
	}
	
	// Work out the permissions	
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
		die("You do not have access to update this page.");
	}
	
	$resources = array();
	$crops = array();
	$fails = array();
	
	$_POST["template"] = $pdata["template"];
	
	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");
	
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
	
	if (count($crops)) {
		include BigTree::path("admin/modules/pages/_front-end-crop.php");
	} elseif (count($fails)) {
		include BigTree::path("admin/modules/pages/_front-end-failed.php");
	} else {
?>
<script type="text/javascript">parent.bigtree_bar_refresh("<?=$refresh_link?>");</script>
<?
	}
	
	die();
?>